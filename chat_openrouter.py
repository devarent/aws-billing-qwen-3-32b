"""
Script CLI untuk memanggil model Qwen dan lainnya menggunakan OpenRouter API.
Mendukung streaming, kalkulasi token, dan estimasi biaya per pemanggilan.
OpenRouter menyediakan akses ke banyak model termasuk Qwen 2.5 32B, 72B, QwQ, dll.
"""

import os
import sys
import time
from dotenv import load_dotenv

# Muat variabel dari file .env jika ada
load_dotenv()

try:
    from openai import OpenAI
except ImportError:
    print("[ERROR] Library 'openai' belum terinstalled.")
    print("Jalankan: pip install -r requirements.txt")
    sys.exit(1)

# Ambil API key
api_key = os.environ.get("OPENROUTER_API_KEY")
if not api_key:
    print("\n[PERINGATAN] OPENROUTER_API_KEY belum diatur di environment variable!")
    api_key = input("Masukkan OpenRouter API Key Anda (sk-or-...): ").strip()
    if not api_key:
        print("[GAGAL] API Key tidak boleh kosong.")
        sys.exit(1)

client = OpenAI(
    base_url="https://openrouter.ai/api/v1",
    api_key=api_key
)

# Model yang tersedia dengan pricing (per 1M tokens)
MODELS = {
    "1": {
        "id": "qwen/qwen-2.5-coder-32b-instruct",
        "name": "Qwen 2.5 Coder 32B",
        "input": 0.66,
        "output": 1.00
    },
    "2": {
        "id": "qwen/qwen-2.5-72b-instruct",
        "name": "Qwen 2.5 72B Instruct",
        "input": 0.35,
        "output": 0.40
    },
    "3": {
        "id": "qwen/qwq-32b-preview",
        "name": "Qwen QwQ 32B Preview (Reasoning)",
        "input": 0.45,
        "output": 3.50
    },
    "4": {
        "id": "qwen/qwen-2-vl-72b-instruct",
        "name": "Qwen 2 VL 72B (Vision)",
        "input": 0.29,
        "output": 1.00
    },
    "5": {
        "id": "qwen/qwen-plus",
        "name": "Qwen Plus 131K",
        "input": 0.26,
        "output": 0.78
    },
    "6": {
        "id": "qwen/qwen3-vl-30b",
        "name": "Qwen 3 VL 30B",
        "input": 0.29,
        "output": 1.00
    }
}

# Model default
current_model = MODELS["1"]

def show_models():
    print("\nModel tersedia di OpenRouter:")
    for key, model in MODELS.items():
        print(f"  {key}. {model['name']} - In: ${model['input']}/1M, Out: ${model['output']}/1M")

def check_balance():
    """Cek saldo OpenRouter"""
    try:
        response = client.auth.key()
        if hasattr(response, 'data'):
            data = response.data
            usage = getattr(data, 'usage', 0)
            limit = getattr(data, 'limit', 0)
            print(f"\n[Saldo OpenRouter] Used: ${usage:.2f} / Limit: ${limit:.2f}")
        else:
            print("\n[Gagal] Tidak dapat membaca saldo.")
    except Exception as e:
        print(f"\n[Error] Gagal cek saldo: {e}")

def run_chat():
    global current_model

    print("=" * 60)
    print(f" OpenRouter AI Client - Model: {current_model['name']}")
    print(f" Estimasi Biaya: ${current_model['input']}/1M Input, ${current_model['output']}/1M Output")
    print(" Ketik 'exit' atau 'quit' untuk keluar.")
    print(" Ketik '/model' untuk ganti model.")
    print(" Ketik '/balance' untuk cek saldo OpenRouter.")
    print("=" * 60)

    messages = [
        {"role": "system", "content": "You are a helpful, expert AI coding assistant."}
    ]

    while True:
        try:
            user_input = input("\nAnda: ").strip()
            if not user_input:
                continue
            if user_input.lower() in ("exit", "quit"):
                print("Sampai jumpa!")
                break

            # Command untuk ganti model
            if user_input.lower() == "/model":
                show_models()
                model_choice = input("Pilih nomor model: ").strip()
                if model_choice in MODELS:
                    current_model = MODELS[model_choice]
                    print(f"Model diubah ke: {current_model['name']}")
                else:
                    print("Pilihan tidak valid.")
                continue

            # Command untuk cek saldo
            if user_input.lower() == "/balance":
                check_balance()
                continue

            messages.append({"role": "user", "content": user_input})

            print("\nAI: ", end="", flush=True)
            start_time = time.time()

            # Panggilan streaming ke OpenRouter
            stream = client.chat.completions.create(
                model=current_model["id"],
                messages=messages,
                temperature=0.6,
                max_tokens=2048,
                stream=True,
                extra_headers={
                    "HTTP-Referer": "http://localhost:8080",
                    "X-Title": "AI Billing Hub"
                }
            )

            assistant_reply = ""
            prompt_tokens = 0
            completion_tokens = 0

            for chunk in stream:
                delta = chunk.choices[0].delta
                content = delta.content or ""
                assistant_reply += content
                print(content, end="", flush=True)

                # Update token count jika tersedia
                if hasattr(chunk, 'usage') and chunk.usage:
                    prompt_tokens = chunk.usage.prompt_tokens or prompt_tokens
                    completion_tokens = chunk.usage.completion_tokens or completion_tokens

            duration = time.time() - start_time
            print()

            messages.append({"role": "assistant", "content": assistant_reply})

            # Estimasi token jika tidak tersedia dari API
            if prompt_tokens == 0 and completion_tokens == 0:
                est_in_tokens = int(len(user_input.split()) * 1.3)
                est_out_tokens = int(len(assistant_reply.split()) * 1.3)
            else:
                est_in_tokens = prompt_tokens
                est_out_tokens = completion_tokens

            cost = ((est_in_tokens / 1_000_000) * current_model['input']) + ((est_out_tokens / 1_000_000) * current_model['output'])

            print(f"\n[Stats] Durasi: {duration:.2f}s | In: {est_in_tokens} tok | Out: {est_out_tokens} tok | Est. Biaya: ${cost:.6f}")

        except KeyboardInterrupt:
            print("\nSesi dihentikan.")
            break
        except Exception as e:
            print(f"\n[Error] {e}")

if __name__ == "__main__":
    run_chat()
