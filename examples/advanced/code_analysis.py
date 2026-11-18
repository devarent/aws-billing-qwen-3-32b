"""
Code Analysis with Qwen3-Next on Amazon Bedrock
Demonstrates deep code understanding and optimization suggestions
"""

import boto3
import json

class CodeAnalyzer:
    """Analyze code using Qwen3-Next"""

    def __init__(self, region="us-east-1"):
        self.client = boto3.client("bedrock-runtime", region_name=region)
        self.model_id = "qwen.qwen3-next-80b-a3b"

    def analyze_code(self, code: str, language: str = "python") -> Dict:
        """
        Analyze code for bugs, performance issues, and best practices

        Args:
            code: Source code to analyze
            language: Programming language

        Returns:
            Analysis results with suggestions
        """
        prompt = f"""
        Analyze this {language} code and provide:

        1. **Bug Detection**: Identify any potential bugs or errors
        2. **Performance Issues**: Identify performance bottlenecks
        3. **Best Practices**: Suggest improvements following best practices
        4. **Security Issues**: Identify security vulnerabilities
        5. **Code Quality**: Rate overall code quality (1-10) with explanation

        Code:
        ```{language}
        {code}
        ```

        Provide specific, actionable recommendations with code examples where relevant.
        """

        response = self.client.converse(
            modelId=self.model_id,
            messages=[{"role": "user", "content": [{"text": prompt}]}],
            inferenceConfig={"maxTokens": 4096, "temperature": 0.3}
        )

        return {
            "analysis": response["output"]["message"]["content"][0]["text"],
            "usage": response.get("usage", {})
        }

    def optimize_code(self, code: str, language: str = "python") -> Dict:
        """
        Optimize code for better performance and readability

        Args:
            code: Source code to optimize
            language: Programming language

        Returns:
            Optimized code with explanation
        """
        prompt = f"""
        Optimize this {language} code for:
        - Better performance
        - Improved readability
        - Reduced memory usage
        - Better error handling

        Code:
        ```{language}
        {code}
        ```

        Provide:
        1. Optimized code
        2. Explanation of changes
        3. Performance improvements expected
        """

        response = self.client.converse(
            modelId=self.model_id,
            messages=[{"role": "user", "content": [{"text": prompt}]}],
            inferenceConfig={"maxTokens": 4096, "temperature": 0.3}
        )

        return {
            "optimized_code": response["output"]["message"]["content"][0]["text"],
            "usage": response.get("usage", {})
        }

    def explain_code(self, code: str, language: str = "python") -> Dict:
        """
        Explain code functionality in detail

        Args:
            code: Source code to explain
            language: Programming language

        Returns:
            Detailed explanation
        """
        prompt = f"""
        Explain this {language} code in detail:
        - What it does
        - How it works
        - Key algorithms or patterns used
        - Time and space complexity

        Code:
        ```{language}
        {code}
        ```

        Make it understandable for someone with intermediate programming knowledge.
        """

        response = self.client.converse(
            modelId=self.model_id,
            messages=[{"role": "user", "content": [{"text": prompt}]}],
            inferenceConfig={"maxTokens": 2048, "temperature": 0.3}
        )

        return {
            "explanation": response["output"]["message"]["content"][0]["text"],
            "usage": response.get("usage", {})
        }

# Example usage
if __name__ == "__main__":
    analyzer = CodeAnalyzer()

    # Sample code to analyze
    sample_code = """
def process_data(data_list):
    result = []
    for item in data_list:
        for i in range(len(data_list)):
            if item == data_list[i]:
                result.append(item)
    return result

def calculate_sum(numbers):
    total = 0
    for num in numbers:
        total = total + num
    return total
    """

    print("="*60)
    print("🔍 Code Analysis")
    print("="*60)
    analysis = analyzer.analyze_code(sample_code, "python")
    print(analysis["analysis"])
    print(f"\n💰 Tokens: {analysis['usage']['inputTokens'] + analysis['usage']['outputTokens']:,}")

    print("\n" + "="*60)
    print("⚡ Code Optimization")
    print("="*60)
    optimized = analyzer.optimize_code(sample_code, "python")
    print(optimized["optimized_code"])
    print(f"\n💰 Tokens: {optimized['usage']['inputTokens'] + optimized['usage']['outputTokens']:,}")

    print("\n" + "="*60)
    print("📖 Code Explanation")
    print("="*60)
    explanation = analyzer.explain_code(sample_code, "python")
    print(explanation["explanation"])
    print(f"\n💰 Tokens: {explanation['usage']['inputTokens'] + explanation['usage']['outputTokens']:,}")
