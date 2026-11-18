"""
Multilingual Processing with Qwen3-Next on Amazon Bedrock
Demonstrates cross-lingual understanding and translation
"""

import boto3
import json

class MultilingualProcessor:
    """Process multilingual text using Qwen3-Next"""

    def __init__(self, region="us-east-1"):
        self.client = boto3.client("bedrock-runtime", region_name=region)
        self.model_id = "qwen.qwen3-next-80b-a3b"

    def translate(self, text: str, source_lang: str, target_lang: str) -> Dict:
        """
        Translate text from source to target language

        Args:
            text: Text to translate
            source_lang: Source language
            target_lang: Target language

        Returns:
            Translated text
        """
        prompt = f"""
        Translate the following text from {source_lang} to {target_lang}:

        Text: {text}

        Provide only the translation, maintaining the original meaning and tone.
        """

        response = self.client.converse(
            modelId=self.model_id,
            messages=[{"role": "user", "content": [{"text": prompt}]}],
            inferenceConfig={"maxTokens": 2048, "temperature": 0.3}
        )

        return {
            "translation": response["output"]["message"]["content"][0]["text"],
            "usage": response.get("usage", {})
        }

    def detect_language(self, text: str) -> Dict:
        """
        Detect the language of given text

        Args:
            text: Text to analyze

        Returns:
            Detected language with confidence
        """
        prompt = f"""
        Detect the language of this text and provide:
        1. Language name
        2. Language code (ISO 639-1)
        3. Confidence level (high/medium/low)

        Text: {text}
        """

        response = self.client.converse(
            modelId=self.model_id,
            messages=[{"role": "user", "content": [{"text": prompt}]}],
            inferenceConfig={"maxTokens": 512, "temperature": 0.2}
        )

        return {
            "detection": response["output"]["message"]["content"][0]["text"],
            "usage": response.get("usage", {})
        }

    def cross_lingual_summarize(self, text: str, target_lang: str) -> Dict:
        """
        Summarize text in target language

        Args:
            text: Text to summarize
            target_lang: Language for summary

        Returns:
            Summary in target language
        """
        prompt = f"""
        Summarize the following text in {target_lang}:

        Text: {text}

        Provide a concise summary (2-3 sentences) capturing the main points.
        """

        response = self.client.converse(
            modelId=self.model_id,
            messages=[{"role": "user", "content": [{"text": prompt}]}],
            inferenceConfig={"maxTokens": 1024, "temperature": 0.5}
        )

        return {
            "summary": response["output"]["message"]["content"][0]["text"],
            "usage": response.get("usage", {})
        }

# Example usage
if __name__ == "__main__":
    processor = MultilingualProcessor()

    # Example 1: English to Indonesian
    text_en = "Artificial intelligence is transforming industries worldwide. From healthcare to finance, AI systems are improving efficiency and enabling new capabilities."

    print("="*60)
    print("🌐 Translation: English → Indonesian")
    print("="*60)
    result1 = processor.translate(text_en, "English", "Indonesian")
    print(f"Original: {text_en}")
    print(f"Translation: {result1['translation']}")
    print(f"💰 Tokens: {result1['usage']['inputTokens'] + result1['usage']['outputTokens']:,}")

    # Example 2: Language detection
    text_id = "Teknologi semakin canggih setiap hari. Kita harus belajar untuk beradaptasi."

    print("\n" + "="*60)
    print("🔍 Language Detection")
    print("="*60)
    result2 = processor.detect_language(text_id)
    print(f"Text: {text_id}")
    print(f"Detection: {result2['detection']}")
    print(f"💰 Tokens: {result2['usage']['inputTokens'] + result2['usage']['outputTokens']:,}")

    # Example 3: Cross-lingual summarization
    long_text = """
    Machine learning is a subset of artificial intelligence that enables systems to learn and improve from experience without being explicitly programmed. It focuses on developing algorithms that can access data and use it to learn for themselves. The learning process begins with observations or data, such as examples, direct experience, or instruction, in order to look for patterns in data and make better decisions in the future based on the examples that we provide. The primary aim is to allow the computers to learn automatically without human intervention or assistance and adjust actions accordingly.
    """

    print("\n" + "="*60)
    print("📝 Cross-lingual Summarization: English → Indonesian")
    print("="*60)
    result3 = processor.cross_lingual_summarize(long_text, "Indonesian")
    print(f"Summary: {result3['summary']}")
    print(f"💰 Tokens: {result3['usage']['inputTokens'] + result3['usage']['outputTokens']:,}")
