# Cek tool yang terpasang beserta versinya
$ErrorActionPreference = "SilentlyContinue"

$checks = [ordered]@{
    "PHP"            = "php -v"
    "Composer"       = "composer --version"
    "Python"         = "python --version"
    "pip"            = "pip --version"
    "Node.js"        = "node --version"
    "npm"            = "npm --version"
    "Git"            = "git --version"
    "Docker"         = "docker --version"
    "Docker Compose" = "docker compose version"
    "kubectl"        = "kubectl version --client"
    "Helm"           = "helm version --short"
    "Terraform"      = "terraform version"
    "Laravel"        = "laravel --version"
    "Java"           = "java -version"
    "Go"             = "go version"
    ".NET"           = "dotnet --version"
    "Rust"           = "rustc --version"
    "GCC"            = "gcc --version"
    "Make"           = "make --version"
}

foreach ($name in $checks.Keys) {
    $cmd = $checks[$name]
    Write-Host ("--- {0} ---" -f $name)
    try {
        Invoke-Expression $cmd 2>&1 | Select-Object -First 3 | ForEach-Object { Write-Host $_ }
    } catch {
        Write-Host "TIDAK TERSEDIA"
    }
    Write-Host ""
}
