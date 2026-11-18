"""
Script CLI untuk memanggil Foundation Model di Amazon Bedrock.
Memanfaatkan Saldo Kredit $200 AWS Mahasiswa/Developer.
Mendukung Qwen3-Next, Anthropic Claude, Meta Llama, dan Amazon Nova.
"""

import os
import sys
import json
import time
from dotenv import load_dotenv

load_dotenv()

try:
    import boto3
except ImportError:
    print("[ERROR] Library 'boto3' belum terinstalled.")
    print("Jalankan: pip install -r requirements.txt")
    sys.exit(1)

REGION = os.environ.get("AWS_DEFAULT_REGION", "us-east-1")

# Harga dalam USD per 1 juta token jika diketahui.
MODELS = {
    "1": {
        "id": "anthropic.claude-3-5-sonnet-20241022-v2:0",
        "name": "Claude 3.5 Sonnet v2",
        "input": 3.00,
        "output": 15.00,
        "provider": "anthropic"
    },
    "2": {
        "id": "anthropic.claude-3-5-haiku-20241022-v1:0",
        "name": "Claude 3.5 Haiku",
        "input": 1.00,
        "output": 5.00,
        "provider": "anthropic"
    },
    "3": {
        "id": "meta.llama3-3-70b-instruct-v1:0",
        "name": "Llama 3.3 70B",
        "input": 0.72,
        "output": 0.72,
        "provider": "meta"
    },
    "4": {
        "id": "meta.llama3-1-70b-instruct-v1:0",
        "name": "Llama 3.1 70B",
        "input": 0.59,
        "output": 0.79,
        "provider": "meta"
    },
    "5": {
        "id": "amazon.nova-lite-v1:0",
        "name": "Amazon Nova Lite",
        "input": 0.15,
        "output": 0.60,
        "provider": "amazon"
    },
    "6": {
        "id": "amazon.nova-pro-v1:0",
        "name": "Amazon Nova Pro",
        "input": 0.80,
        "output": 3.20,
        "provider": "amazon"
    },
    "7": {
        "id": "qwen.qwen3-next-80b-a3b",
        "name": "Qwen3-Next 80B A3B",
        "input": None,
        "output": None,
        "provider": "qwen"
    }
}

# Model default
current_model = MODELS["1"]

def show_models():
    print("\nModel tersedia di AWS Bedrock:")
    for key, model in MODELS.items():
        if model["input"] is None or model["output"] is None:
            pricing = "Tarif: cek AWS Bedrock Pricing untuk region/service tier"
        else:
            pricing = f"In: ${model['input']}/1M, Out: ${model['output']}/1M"
        print(f"  {key}. {model['name']} - {pricing}")

def get_bedrock_client():
    try:
        # Menggunakan kredensial dari environment variable atau ~/.aws/credentials
        client = boto3.client("bedrock-runtime", region_name=REGION)
        return client
    except Exception as e:
        print(f"[ERROR] Gagal inisialisasi AWS Bedrock client: {e}")
        print("Pastikan Anda sudah menjalankan 'aws configure' atau mengisi .env file.")
        sys.exit(1)

def invoke_model(client, prompt, model_id, provider):
    """Panggil model Bedrock dengan format yang sesuai"""
    start = time.time()

    if provider == "anthropic":
        payload = {
            "anthropic_version": "bedrock-2023-05-31",
            "max_tokens": 1024,
            "temperature": 0.6,
            "messages": [{"role": "user", "content": prompt}]
        }
    elif provider == "meta":
        payload = {
            "max_gen_len": 1024,
            "temperature": 0.6,
            "prompt": prompt
        }
    elif provider == "amazon":
        payload = {
            "inferenceConfig": {
                "maxTokens": 1024,
                "temperature": 0.6
            },
            "messages": [{"role": "user", "content": [{"text": prompt}]}]
        }
    elif provider == "qwen":
        response = client.converse(
            modelId=model_id,
            messages=[{"role": "user", "content": [{"text": prompt}]}],
            inferenceConfig={"maxTokens": 1024, "temperature": 0.6}
        )
        duration = time.time() - start
        text = response["output"]["message"]["content"][0]["text"]
        usage = response.get("usage", {})
        in_tok = usage.get("inputTokens", 0)
        out_tok = usage.get("outputTokens", 0)
        return text, in_tok, out_tok, duration
    else:
        raise ValueError(f"Provider tidak didukung: {provider}")

    response = client.invoke_model(
        modelId=model_id,
        body=json.dumps(payload),
        contentType="application/json",
        accept="application/json"
    )
    duration = time.time() - start

    response_body = json.loads(response["body"].read())

    # Parse response berdasarkan provider
    if provider == "anthropic":
        text = response_body["content"][0]["text"]
        usage = response_body.get("usage", {})
        in_tok = usage.get("input_tokens", 0)
        out_tok = usage.get("output_tokens", 0)
    elif provider == "meta":
        text = response_body["generation"]
        in_tok = response_body.get("prompt_token_count", 0)
        out_tok = response_body.get("generation_token_count", 0)
    elif provider == "amazon":
        text = response_body["output"]["message"]["content"][0]["text"]
        usage = response_body.get("usage", {})
        in_tok = usage.get("inputTokens", 0)
        out_tok = usage.get("outputTokens", 0)
    else:
        text = str(response_body)
        in_tok = 0
        out_tok = 0

    return text, in_tok, out_tok, duration

def main():
    global current_model

    print("=" * 60)
    print(f" Amazon Bedrock AI Client (Memakai Saldo $200 AWS)")
    print(f" Model: {current_model['name']}")
    print(f" Region: {REGION}")
    print(" Ketik 'exit' atau 'quit' untuk keluar.")
    print(" Ketik '/model' untuk ganti model.")
    print("=" * 60)

    client = get_bedrock_client()

    while True:
        try:
            prompt = input("\nAnda: ").strip()
            if not prompt:
                continue
            if prompt.lower() in ("exit", "quit"):
                print("Sampai jumpa!")
                break

            # Command untuk ganti model
            if prompt.lower() == "/model":
                show_models()
                model_choice = input("Pilih nomor model: ").strip()
                if model_choice in MODELS:
                    current_model = MODELS[model_choice]
                    print(f"Model diubah ke: {current_model['name']}")
                else:
                    print("Pilihan tidak valid.")
                continue

            print("\nMemproses via Bedrock...", end="\r", flush=True)
            text, in_tok, out_tok, dur = invoke_model(
                client,
                prompt,
                current_model["id"],
                current_model["provider"]
            )

            print(f"AI: {text}")

            stats = f"\n[Stats] Durasi: {dur:.2f}s | In: {in_tok} tok | Out: {out_tok} tok"
            if current_model["input"] is None or current_model["output"] is None:
                print(stats + " | Estimasi biaya: tarif perlu dicek di AWS Bedrock Pricing untuk region/service tier.")
            else:
                cost = ((in_tok / 1_000_000) * current_model['input']) + ((out_tok / 1_000_000) * current_model['output'])
                print(stats + f" | Estimasi biaya: ${cost:.6f}")

        except KeyboardInterrupt:
            print("\nSesi dihentikan.")
            break
        except Exception as e:
            print(f"\n[Error Bedrock] {e}")

if __name__ == "__main__":
    main()
