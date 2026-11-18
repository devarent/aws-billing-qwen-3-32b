"""
Script CLI untuk memanggil model Qwen (dan model lainnya) menggunakan Groq API.
Mendukung streaming, kalkulasi token, dan estimasi biaya per pemanggilan.
"""

import os
import sys
import time
from dotenv import load_dotenv

# Muat variabel dari file .env jika ada
load_dotenv()

try:
    from groq import Groq
except ImportError:
    print("[ERROR] Library 'groq' belum terinstall.")
    print("Jalankan: pip install -r requirements.txt")
    sys.exit(1)

# Ambil API key
api_key = os.environ.get("GROQ_API_KEY")
if not api_key:
    print("\n[PERINGATAN] GROQ_API_KEY belum diatur di environment variable!")
    api_key = input("Masukkan Groq API Key Anda (gsk_...): ").strip()
    if not api_key:
        print("[GAGAL] API Key tidak boleh kosong.")
        sys.exit(1)

client = Groq(api_key=api_key)

# Model yang tersedia dengan pricing
MODELS = {
    "1": {
        "id": "qwen/qwen-2.5-coder-32b-instruct",
        "name": "Qwen 2.5 Coder 32B",
        "input": 0.66,
        "output": 1.00
    },
    "2": {
        "id": "qwen/qwen3.8-27b",
        "name": "Qwen 3.8 27B",
        "input": 0.19,
        "output": 0.19
    },
    "3": {
        "id": "qwen/qwen-2.5-32b-instruct",
        "name": "Qwen 2.5 32B Instruct",
        "input": 0.35,
        "output": 0.40
    },
    "4": {
        "id": "llama-3.3-70b-versatile",
        "name": "Llama 3.3 70B",
        "input": 0.59,
        "output": 0.79
    },
    "5": {
        "id": "llama-3.1-70b-versatile",
        "name": "Llama 3.1 70B",
        "input": 0.59,
        "output": 0.79
    },
    "6": {
        "id": "mixtral-8x7b-32768",
        "name": "Mixtral 8x7B",
        "input": 0.27,
        "output": 0.27
    }
}

# Model default
current_model = MODELS["1"]

def show_models():
    print("\nModel tersedia:")
    for key, model in MODELS.items():
        print(f"  {key}. {model['name']} - In: ${model['input']}/1M, Out: ${model['output']}/1M")

def run_chat():
    global current_model

    print("=" * 60)
    print(f" Groq AI Client - Model: {current_model['name']}")
    print(f" Estimasi Biaya: ${current_model['input']}/1M Input, ${current_model['output']}/1M Output")
    print(" Ketik 'exit' atau 'quit' untuk keluar.")
    print(" Ketik '/model' untuk ganti model.")
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

            messages.append({"role": "user", "content": user_input})

            print("\nQwen: ", end="", flush=True)
            start_time = time.time()

            # Panggilan streaming ke Groq
            stream = client.chat.completions.create(
                model=current_model["id"],
                messages=messages,
                temperature=0.6,
                max_completion_tokens=2048,
                top_p=0.95,
                stream=True
            )

            assistant_reply = ""
            for chunk in stream:
                delta = chunk.choices[0].delta.content or ""
                assistant_reply += delta
                print(delta, end="", flush=True)

            duration = time.time() - start_time
            print()

            messages.append({"role": "assistant", "content": assistant_reply})

            # Estimasi token kasar (1 kata ~ 1.3 token)
            est_in_tokens = int(len(user_input.split()) * 1.3)
            est_out_tokens = int(len(assistant_reply.split()) * 1.3)
            cost = ((est_in_tokens / 1_000_000) * current_model['input']) + ((est_out_tokens / 1_000_000) * current_model['output'])

            print(f"\n[Stats] Durasi: {duration:.2f}s | Est. Tokens: ~{est_in_tokens + est_out_tokens} | Est. Biaya: ${cost:.6f}")

        except KeyboardInterrupt:
            print("\nSesi dihentikan.")
            break
        except Exception as e:
            print(f"\n[Error] {e}")

if __name__ == "__main__":
    run_chat()
