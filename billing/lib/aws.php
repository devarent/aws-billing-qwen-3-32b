<?php
/**
 * Integrasi AWS untuk AWS Billing Manager.
 *
 * Implementasi ringan tanpa AWS SDK (hanya cURL + Signature Version 4):
 *   - STS GetCallerIdentity          → validasi kredensial & Account ID
 *   - Cost Explorer GetCostAndUsage  → tarik biaya harian per layanan
 *   - AWS Budgets DescribeBudgets    → tarik limit budget
 *
 * Kredensial dibaca dari environment / .env:
 *   AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_SESSION_TOKEN (opsional),
 *   AWS_DEFAULT_REGION.
 *
 * Kunci yang dipakai sebaiknya read-only (lihat docs/AWS_BILLING_SETUP.md).
 */

declare(strict_types=1);

class AwsApiException extends RuntimeException
{
    public string $errorCode;
    public int $httpStatus;

    public function __construct(string $message, string $errorCode = '', int $httpStatus = 0)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
    }
}

/** Konfigurasi AWS efektif. */
function aws_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $region = env_value('AWS_DEFAULT_REGION', env_value('AWS_REGION', 'us-east-1'));
    $cfg = [
        'access_key'    => env_value('AWS_ACCESS_KEY_ID'),
        'secret_key'    => env_value('AWS_SECRET_ACCESS_KEY'),
        'session_token' => env_value('AWS_SESSION_TOKEN'),
        'region'        => $region !== '' ? $region : 'us-east-1',
        'sync_days'     => max(1, min(365, (int) env_value('AWS_SYNC_DAYS', '30'))),
    ];
    return $cfg;
}

/** True bila nilai masih berupa placeholder template. */
function aws_is_placeholder(string $value): bool
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return true;
    }
    foreach (['your_', 'your-', 'akia_your', 'replace_with', 'replace-', 'changeme', 'change_me', 'example', 'xxx', 'todo'] as $needle) {
        if (str_starts_with($value, $needle) || str_contains($value, $needle)) {
            return true;
        }
    }
    return false;
}

/** True bila access key & secret terisi dan bukan placeholder. */
function aws_is_configured(): bool
{
    $cfg = aws_config();
    if ($cfg['access_key'] === '' || $cfg['secret_key'] === '') {
        return false;
    }
    return !aws_is_placeholder($cfg['access_key']) && !aws_is_placeholder($cfg['secret_key']);
}

/** Access key yang disamarkan untuk ditampilkan di UI. */
function aws_masked_access_key(): string
{
    $key = aws_config()['access_key'];
    if ($key === '') {
        return '—';
    }
    if (strlen($key) <= 8) {
        return str_repeat('•', strlen($key));
    }
    return substr($key, 0, 4) . str_repeat('•', 8) . substr($key, -4);
}

/** URI encode sesuai aturan SigV4. */
function aws_uri_encode(string $value, bool $encodeSlash = true): string
{
    $out = '';
    $length = strlen($value);
    for ($i = 0; $i < $length; $i++) {
        $char = $value[$i];
        if (($char >= 'A' && $char <= 'Z') || ($char >= 'a' && $char <= 'z')
            || ($char >= '0' && $char <= '9') || $char === '-' || $char === '_'
            || $char === '.' || $char === '~') {
            $out .= $char;
        } elseif ($char === '/' && !$encodeSlash) {
            $out .= '/';
        } else {
            $out .= '%' . strtoupper(bin2hex($char));
        }
    }
    return $out;
}

/** HMAC berantai untuk kunci turunan SigV4. */
function aws_signature_key(string $secret, string $dateStamp, string $region, string $service): string
{
    $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $secret, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    return hash_hmac('sha256', 'aws4_request', $kService, true);
}

/** Potong string dengan aman walau ekstensi mbstring tidak aktif. */
function aws_str_limit(string $value, int $length): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $length);
    }
    return substr($value, 0, $length);
}

/**
 * Kirim request bertanda tangan SigV4.
 *
 * @param array<string,string> $query
 * @param array<string,string> $extraHeaders
 * @return array{status:int, body:string, json:?array}
 */
function aws_request(
    string $service,
    string $region,
    string $method,
    string $host,
    string $path,
    array $query,
    string $body,
    array $extraHeaders = []
): array {
    $cfg = aws_config();
    if (!aws_is_configured()) {
        $hasKeys = $cfg['access_key'] !== '' && $cfg['secret_key'] !== '';
        $reason = $hasKeys ? 'masih berupa placeholder' : 'belum diisi';
        throw new AwsApiException(
            'Kredensial AWS ' . $reason . '. Isi AWS_ACCESS_KEY_ID dan AWS_SECRET_ACCESS_KEY di .env, '
            . 'lalu jalankan "docker compose up -d --force-recreate ai-billing-hub".',
            'CredentialsMissing'
        );
    }
    if (!function_exists('curl_init')) {
        throw new AwsApiException(
            'Ekstensi PHP cURL belum aktif. Pasang php-curl (Ubuntu: sudo apt install php-curl) lalu restart server.',
            'CurlMissing'
        );
    }

    $method = strtoupper($method);
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $amzDate = $now->format('Ymd\THis\Z');
    $dateStamp = $now->format('Ymd');
    $payloadHash = hash('sha256', $body);

    // Query canonical: urutkan berdasarkan key lalu value.
    $queryPairs = [];
    foreach ($query as $key => $value) {
        $queryPairs[] = [aws_uri_encode((string) $key), aws_uri_encode((string) $value)];
    }
    usort($queryPairs, static function (array $a, array $b): int {
        return $a[0] === $b[0] ? strcmp($a[1], $b[1]) : strcmp($a[0], $b[0]);
    });
    $canonicalQuery = implode('&', array_map(
        static fn (array $pair): string => $pair[0] . '=' . $pair[1],
        $queryPairs
    ));

    $headers = [
        'host'                 => $host,
        'x-amz-content-sha256' => $payloadHash,
        'x-amz-date'           => $amzDate,
    ];
    if ($cfg['session_token'] !== '') {
        $headers['x-amz-security-token'] = $cfg['session_token'];
    }
    foreach ($extraHeaders as $key => $value) {
        $headers[strtolower(trim($key))] = trim($value);
    }
    ksort($headers);

    $canonicalHeaders = '';
    foreach ($headers as $key => $value) {
        $canonicalHeaders .= $key . ':' . preg_replace('/\s+/', ' ', $value) . "\n";
    }
    $signedHeaders = implode(';', array_keys($headers));
    $canonicalUri = $path === '' ? '/' : $path;

    $canonicalRequest = implode("\n", [
        $method,
        $canonicalUri,
        $canonicalQuery,
        $canonicalHeaders,
        $signedHeaders,
        $payloadHash,
    ]);

    $scope = $dateStamp . '/' . $region . '/' . $service . '/aws4_request';
    $stringToSign = implode("\n", [
        'AWS4-HMAC-SHA256',
        $amzDate,
        $scope,
        hash('sha256', $canonicalRequest),
    ]);

    $signingKey = aws_signature_key($cfg['secret_key'], $dateStamp, $region, $service);
    $signature = hash_hmac('sha256', $stringToSign, $signingKey);
    $authorization = 'AWS4-HMAC-SHA256 Credential=' . $cfg['access_key'] . '/' . $scope
        . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;

    $headerLines = ['Authorization: ' . $authorization, 'Expect:'];
    foreach ($headers as $key => $value) {
        $headerLines[] = $key . ': ' . $value;
    }

    $url = 'https://' . $host . $canonicalUri;
    if ($canonicalQuery !== '') {
        $url .= '?' . $canonicalQuery;
    }

    $ch = curl_init($url);
    if ($ch === false) {
        throw new AwsApiException('Tidak bisa membuat koneksi cURL ke AWS.', 'CurlInit');
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headerLines,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($method !== 'GET' && $method !== 'HEAD') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);

    if ($response === false) {
        throw new AwsApiException('Gagal menghubungi AWS: ' . ($curlError ?: 'unknown error'), 'NetworkError');
    }

    $json = json_decode((string) $response, true);
    if (!is_array($json)) {
        $json = null;
    }

    if ($status < 200 || $status >= 300) {
        [$code, $message] = aws_parse_error((string) $response, $json);
        throw new AwsApiException($message !== '' ? $message : 'AWS menolak request (' . $status . ').', $code, $status);
    }

    return ['status' => $status, 'body' => (string) $response, 'json' => $json];
}

/** Ambil kode & pesan error dari respons AWS (JSON atau XML). */
function aws_parse_error(string $body, ?array $json): array
{
    if (is_array($json)) {
        $code = (string) ($json['__type'] ?? $json['code'] ?? $json['Code'] ?? '');
        $code = preg_replace('/^.*#/', '', $code) ?? $code;
        $message = (string) ($json['message'] ?? $json['Message'] ?? '');
        return [$code, $message];
    }

    if (str_contains($body, '<ErrorResponse') || str_contains($body, '<Error>')) {
        if (function_exists('simplexml_load_string')) {
            $previous = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            if ($xml !== false) {
                $code = (string) ($xml->Error->Code ?? $xml->Code ?? '');
                $message = (string) ($xml->Error->Message ?? $xml->Message ?? '');
                return [$code, $message];
            }
        }
        // Fallback tanpa SimpleXML.
        $code = preg_match('~<Code>(.*?)</Code>~s', $body, $m) ? trim($m[1]) : '';
        $message = preg_match('~<Message>(.*?)</Message>~s', $body, $m) ? trim($m[1]) : '';
        return [$code, $message];
    }

    return ['', trim(strip_tags(substr($body, 0, 300)))];
}

/** Validasi kredensial dan ambil identitas AWS. */
function aws_sts_identity(): array
{
    $cfg = aws_config();
    $region = $cfg['region'] ?: 'us-east-1';
    $host = 'sts.' . $region . '.amazonaws.com';
    $query = ['Action' => 'GetCallerIdentity', 'Version' => '2011-06-15'];
    $result = aws_request('sts', $region, 'GET', $host, '/', $query, '');
    $body = $result['body'];

    if (function_exists('simplexml_load_string')) {
        $xml = simplexml_load_string($body);
        if ($xml === false) {
            throw new AwsApiException('Respons STS tidak dapat diparse.', 'InvalidResponse');
        }
        return [
            'account' => (string) ($xml->GetCallerIdentityResult->Account ?? ''),
            'arn'     => (string) ($xml->GetCallerIdentityResult->Arn ?? ''),
            'user_id' => (string) ($xml->GetCallerIdentityResult->UserId ?? ''),
        ];
    }

    // Fallback tanpa SimpleXML.
    $read = static function (string $tag) use ($body): string {
        return preg_match('~<' . $tag . '>(.*?)</' . $tag . '>~s', $body, $m) ? trim($m[1]) : '';
    };
    return [
        'account' => $read('Account'),
        'arn'     => $read('Arn'),
        'user_id' => $read('UserId'),
    ];
}

/** Ambil biaya harian per layanan dari Cost Explorer (mendukung pagination). */
function aws_cost_and_usage(string $start, string $end): array
{
    $results = [];
    $token = null;
    $pages = 0;

    do {
        $payload = [
            'TimePeriod'  => ['Start' => $start, 'End' => $end],
            'Granularity' => 'DAILY',
            'Metrics'     => ['UnblendedCost'],
            'GroupBy'     => [['Type' => 'DIMENSION', 'Key' => 'SERVICE']],
            'Filter'      => [
                'Not' => [
                    'Dimensions' => [
                        'Key'    => 'RECORD_TYPE',
                        'Values' => ['Credit', 'Refund', 'Tax'],
                    ],
                ],
            ],
        ];
        if ($token !== null && $token !== '') {
            $payload['NextPageToken'] = $token;
        }
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new AwsApiException('Gagal menyusun payload Cost Explorer.', 'EncodeError');
        }

        $result = aws_request(
            'ce',
            'us-east-1',
            'POST',
            'ce.us-east-1.amazonaws.com',
            '/',
            [],
            $body,
            [
                'Content-Type' => 'application/x-amz-json-1.1',
                'X-Amz-Target' => 'AWSInsightsIndexService.GetCostAndUsage',
            ]
        );

        $json = $result['json'] ?? [];
        foreach (($json['ResultsByTime'] ?? []) as $day) {
            $results[] = $day;
        }
        $token = (string) ($json['NextPageToken'] ?? '');
        $pages++;
    } while ($token !== '' && $pages < 20);

    return ['ResultsByTime' => $results];
}

/** Ambil daftar budget dari AWS Budgets (mendukung pagination). */
function aws_budgets_describe(string $accountId): array
{
    $budgets = [];
    $token = null;
    $pages = 0;

    do {
        $payload = ['AccountId' => $accountId, 'MaxResults' => 100];
        if ($token !== null && $token !== '') {
            $payload['NextToken'] = $token;
        }
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new AwsApiException('Gagal menyusun payload AWS Budgets.', 'EncodeError');
        }

        $result = aws_request(
            'budgets',
            'us-east-1',
            'POST',
            'budgets.amazonaws.com',
            '/',
            [],
            $body,
            [
                'Content-Type' => 'application/x-amz-json-1.1',
                'X-Amz-Target' => 'AWSBudgetServiceGateway.DescribeBudgets',
            ]
        );

        $json = $result['json'] ?? [];
        foreach (($json['Budgets'] ?? []) as $budget) {
            $budgets[] = $budget;
        }
        $token = (string) ($json['NextToken'] ?? '');
        $pages++;
    } while ($token !== '' && $pages < 20);

    return ['Budgets' => $budgets];
}

/** Simpan setting key/value (upsert). */
function setting_set(string $key, string $value): void
{
    db_exec(
        'INSERT INTO settings (skey, svalue) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)',
        [$key, $value]
    );
}

/**
 * Petakan nama layanan Cost Explorer ke katalog layanan lokal.
 *
 * @return array{code:string, name:string, category:string}
 */
function aws_service_meta(string $serviceName): array
{
    $name = trim($serviceName) !== '' ? trim($serviceName) : 'AWS Other';
    $map = [
        'Amazon Elastic Compute Cloud - Compute' => ['EC2', 'Amazon EC2', 'Compute'],
        'EC2 - Other'                            => ['EC2', 'Amazon EC2', 'Compute'],
        'Amazon Simple Storage Service'          => ['S3', 'Amazon S3', 'Storage'],
        'Amazon Relational Database Service'     => ['RDS', 'Amazon RDS', 'Database'],
        'AWS Lambda'                             => ['Lambda', 'AWS Lambda', 'Serverless'],
        'Amazon CloudFront'                      => ['CloudFront', 'Amazon CloudFront', 'Networking'],
        'Amazon Bedrock'                         => ['Bedrock', 'Amazon Bedrock', 'AI/ML'],
        'Amazon DynamoDB'                        => ['DynamoDB', 'Amazon DynamoDB', 'Database'],
        'Amazon Elastic Kubernetes Service'      => ['EKS', 'Amazon EKS', 'Containers'],
        'Amazon Elastic Container Service'       => ['ECS', 'Amazon ECS', 'Containers'],
        'Amazon Simple Notification Service'     => ['SNS', 'Amazon SNS', 'Integration'],
        'Amazon CloudWatch'                      => ['CloudWatch', 'Amazon CloudWatch', 'Management'],
        'Amazon Route 53'                        => ['Route53', 'Amazon Route 53', 'Networking'],
        'Amazon Virtual Private Cloud'           => ['VPC', 'Amazon VPC', 'Networking'],
        'Amazon Elastic Block Store'             => ['EBS', 'Amazon EBS', 'Storage'],
        'AWS Data Transfer'                      => ['DataTransfer', 'AWS Data Transfer', 'Networking'],
        'AWS Key Management Service'             => ['KMS', 'AWS KMS', 'Security'],
        'AWS CloudTrail'                         => ['CloudTrail', 'AWS CloudTrail', 'Management'],
        'Amazon GuardDuty'                       => ['GuardDuty', 'Amazon GuardDuty', 'Security'],
        'Amazon Cognito'                         => ['Cognito', 'Amazon Cognito', 'Security'],
        'Amazon API Gateway'                     => ['APIGateway', 'Amazon API Gateway', 'Integration'],
        'AWS Glue'                                => ['Glue', 'AWS Glue', 'Analytics'],
        'Amazon Athena'                          => ['Athena', 'Amazon Athena', 'Analytics'],
        'Amazon Kinesis'                         => ['Kinesis', 'Amazon Kinesis', 'Analytics'],
        'Amazon Elastic Container Registry'      => ['ECR', 'Amazon ECR', 'Containers'],
        'Amazon SageMaker'                       => ['SageMaker', 'Amazon SageMaker', 'AI/ML'],
        'Amazon OpenSearch Service'              => ['OpenSearch', 'Amazon OpenSearch', 'Analytics'],
        'Amazon ElastiCache'                     => ['ElastiCache', 'Amazon ElastiCache', 'Database'],
        'Amazon Redshift'                        => ['Redshift', 'Amazon Redshift', 'Analytics'],
        'AWS Config'                             => ['Config', 'AWS Config', 'Management'],
        'AWS Secrets Manager'                    => ['SecretsManager', 'AWS Secrets Manager', 'Security'],
        'Amazon Simple Email Service'            => ['SES', 'Amazon SES', 'Integration'],
        'AWS Step Functions'                     => ['StepFunctions', 'AWS Step Functions', 'Serverless'],
        'Amazon EventBridge'                     => ['EventBridge', 'Amazon EventBridge', 'Integration'],
        'AWS WAF'                                => ['WAF', 'AWS WAF', 'Security'],
        'AWS Shield'                             => ['Shield', 'AWS Shield', 'Security'],
        'Amazon Inspector'                       => ['Inspector', 'Amazon Inspector', 'Security'],
        'Amazon Macie'                           => ['Macie', 'Amazon Macie', 'Security'],
        'Amazon Lightsail'                       => ['Lightsail', 'Amazon Lightsail', 'Compute'],
        'Amazon MQ'                              => ['MQ', 'Amazon MQ', 'Integration'],
    ];

    if (isset($map[$name])) {
        [$code, $label, $category] = $map[$name];
        return ['code' => $code, 'name' => $label, 'category' => $category];
    }

    // Heuristik untuk layanan AWS lain yang belum ada di map.
    $lower = strtolower($name);
    $category = 'AWS';
    foreach ([
        'Compute' => ['ec2', 'lambda', 'lightsail', 'batch', 'fargate'],
        'Storage' => ['s3', 'storage', 'glacier', 'backup', 'efs', 'fsx'],
        'Database' => ['rds', 'dynamo', 'aurora', 'database', 'elasticache', 'redshift'],
        'Networking' => ['cloudfront', 'route', 'vpc', 'transfer', 'network', 'direct connect'],
        'Security' => ['guardduty', 'kms', 'waf', 'shield', 'inspector', 'macie', 'secrets', 'cognito'],
        'Analytics' => ['athena', 'glue', 'kinesis', 'emr', 'quicksight', 'opensearch'],
        'AI/ML' => ['bedrock', 'sagemaker', 'comprehend', 'rekognition', 'transcribe'],
    ] as $group => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($lower, $needle)) {
                $category = $group;
                break 2;
            }
        }
    }

    $code = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $name) ?? '');
    $code = substr($code, 0, 38);
    if ($code === '') {
        $code = 'AWSOTHER';
    }

    return ['code' => $code, 'name' => $name, 'category' => $category];
}

/** Cari/buat layanan lokal, kembalikan ID. */
function aws_ensure_service(string $serviceName): int
{
    $meta = aws_service_meta($serviceName);
    $existing = db_row('SELECT id, name FROM services WHERE code = ?', [$meta['code']]);
    if ($existing) {
        return (int) $existing['id'];
    }

    // Hindari bentrok kode bila nama berbeda tetapi sanitasi menghasilkan kode sama.
    $base = $meta['code'];
    $suffix = 2;
    while (db_row('SELECT id FROM services WHERE code = ?', [$meta['code']])) {
        $meta['code'] = substr($base, 0, 34) . '-' . $suffix;
        $suffix++;
        if ($suffix > 99) {
            break;
        }
    }

    db_exec(
        'INSERT INTO services (code, name, category, unit, unit_price) VALUES (?, ?, ?, ?, 0)',
        [$meta['code'], $meta['name'], $meta['category'], 'usage']
    );
    return (int) db()->lastInsertId();
}

/**
 * Sinkronkan biaya Cost Explorer ke database lokal.
 *
 * @return array{account_id:string, local_account_id:int, rows:int, total:float, start:string, end:string}
 */
function aws_sync_costs(int $days = 30, ?array $identity = null): array
{
    $days = max(1, min(365, $days));
    $identity = $identity ?? aws_sts_identity();
    $accountId = (string) ($identity['account'] ?? '');
    if (!preg_match('/^\d{12}$/', $accountId)) {
        throw new AwsApiException('Account ID dari STS tidak valid.', 'InvalidAccount');
    }

    $account = db_row('SELECT * FROM aws_accounts WHERE account_id = ?', [$accountId]);
    if (!$account) {
        $alias = 'AWS Account ' . substr($accountId, -4);
        $email = q_setting('alert_email', '');
        db_exec(
            'INSERT INTO aws_accounts (account_id, alias, environment, owner_email, region, status, credit_balance, monthly_budget)
             VALUES (?, ?, ?, ?, ?, ?, 0, 0)',
            [$accountId, $alias, 'production', $email !== '' ? $email : null, aws_config()['region'], 'active']
        );
        $localAccountId = (int) db()->lastInsertId();
    } else {
        $localAccountId = (int) $account['id'];
    }

    // Cost Explorer memakai End date eksklusif.
    $end = date('Y-m-d', strtotime(today() . ' +1 day'));
    $start = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $data = aws_cost_and_usage($start, $end);

    $rows = 0;
    $total = 0.0;

    db()->beginTransaction();
    try {
        // Idempotensi: buang hasil sync lama untuk rentang yang sama.
        // End bersifat eksklusif (mengikuti Cost Explorer), jadi jangan hapus tanggal end.
        db_exec(
            'DELETE FROM cost_records WHERE account_id = ? AND source = ? AND usage_date >= ? AND usage_date < ?',
            [$localAccountId, 'aws_ce', $start, $end]
        );

        foreach (($data['ResultsByTime'] ?? []) as $day) {
            $usageDate = (string) ($day['TimePeriod']['Start'] ?? '');
            if (!valid_date($usageDate)) {
                continue;
            }
            foreach (($day['Groups'] ?? []) as $group) {
                $serviceName = (string) ($group['Keys'][0] ?? 'AWS Other');
                $amount = (float) ($group['Metrics']['UnblendedCost']['Amount'] ?? 0);
                if (abs($amount) < 0.00001) {
                    continue;
                }
                $serviceId = aws_ensure_service($serviceName);
                db_exec(
                    'INSERT INTO cost_records (account_id, service_id, usage_date, region, usage_quantity, cost_usd, source, note)
                     VALUES (?, ?, ?, ?, 1, ?, ?, ?)',
                    [
                        $localAccountId,
                        $serviceId,
                        $usageDate,
                        aws_config()['region'],
                        round($amount, 4),
                        'aws_ce',
                        'Cost Explorer: ' . aws_str_limit($serviceName, 120),
                    ]
                );
                $rows++;
                $total += $amount;
            }
        }

        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }

    $message = sprintf('%d baris biaya dari %s s/d %s (Cost Explorer).', $rows, $start, $end);
    db_exec(
        'INSERT INTO import_logs (filename, rows_imported, rows_skipped, status, message)
         VALUES (?, ?, 0, ?, ?)',
        ['AWS Cost Explorer · ' . $days . ' hari', $rows, 'success', $message]
    );
    setting_set('aws_last_sync_at', date('Y-m-d H:i:s'));
    setting_set('aws_last_sync_rows', (string) $rows);
    setting_set('aws_last_sync_total', number_format($total, 2, '.', ''));

    return [
        'account_id'       => $accountId,
        'local_account_id' => $localAccountId,
        'rows'             => $rows,
        'total'            => $total,
        'start'            => $start,
        'end'              => $end,
    ];
}

/**
 * Sinkronkan budget dari AWS Budgets.
 *
 * @return array{created:int, updated:int, skipped:int}
 */
function aws_sync_budgets(string $accountId): array
{
    $local = db_row('SELECT * FROM aws_accounts WHERE account_id = ?', [$accountId]);
    if (!$local) {
        return ['created' => 0, 'updated' => 0, 'skipped' => 1];
    }
    $localId = (int) $local['id'];
    $data = aws_budgets_describe($accountId);

    $created = 0;
    $updated = 0;
    $skipped = 0;

    foreach (($data['Budgets'] ?? []) as $budget) {
        $name = trim((string) ($budget['BudgetName'] ?? ''));
        $limit = (float) ($budget['BudgetLimit']['Amount'] ?? 0);
        if ($name === '' || $limit <= 0) {
            $skipped++;
            continue;
        }
        $period = match (strtoupper((string) ($budget['TimeUnit'] ?? 'MONTHLY'))) {
            'QUARTERLY' => 'quarterly',
            'ANNUALLY'  => 'annual',
            default     => 'monthly',
        };

        $existing = db_row('SELECT id FROM budgets WHERE account_id = ? AND name = ?', [$localId, $name]);
        if ($existing) {
            db_exec('UPDATE budgets SET limit_usd = ?, period = ?, active = 1 WHERE id = ?', [$limit, $period, (int) $existing['id']]);
            $updated++;
        } else {
            db_exec(
                'INSERT INTO budgets (account_id, name, period, limit_usd, alert_threshold, hard_stop, active)
                 VALUES (?, ?, ?, ?, ?, 0, 1)',
                [$localId, aws_str_limit($name, 120), $period, $limit, (int) q_setting('budget_alert_threshold', '80')]
            );
            $created++;
        }

        if ($period === 'monthly') {
            db_exec('UPDATE aws_accounts SET monthly_budget = ? WHERE id = ?', [$limit, $localId]);
        }
    }

    return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
}

/**
 * Tes koneksi + simpan hasilnya di settings.
 *
 * @return array{account:string, arn:string, user_id:string}
 */
function aws_test_connection(): array
{
    $identity = aws_sts_identity();
    setting_set('aws_last_test_at', date('Y-m-d H:i:s'));
    setting_set('aws_last_test_ok', '1');
    setting_set('aws_last_test_message', 'Koneksi AWS berhasil.');
    setting_set('aws_identity_account', (string) $identity['account']);
    setting_set('aws_identity_arn', (string) $identity['arn']);
    return $identity;
}

/** Catat kegagalan tes koneksi agar terlihat di UI. */
function aws_record_test_failure(Throwable $e): void
{
    setting_set('aws_last_test_at', date('Y-m-d H:i:s'));
    setting_set('aws_last_test_ok', '0');
    setting_set('aws_last_test_message', aws_str_limit($e->getMessage(), 250));
}
