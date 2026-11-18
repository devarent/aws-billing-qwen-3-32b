"""
Long Context Analysis with Qwen3-Next on Amazon Bedrock
Demonstrates processing and synthesizing lengthy documents
"""

import boto3
import json

class LongContextAnalyzer:
    """Analyze long documents using Qwen3-Next"""

    def __init__(self, region="us-east-1"):
        self.client = boto3.client("bedrock-runtime", region_name=region)
        self.model_id = "qwen.qwen3-next-80b-a3b"

    def summarize_document(self, document: str, max_sentences: int = 5) -> Dict:
        """
        Summarize a long document

        Args:
            document: Long text document
            max_sentences: Maximum sentences in summary

        Returns:
            Document summary
        """
        prompt = f"""
        Summarize this document in {max_sentences} sentences or less:

        Document:
        {document}

        Focus on the most important points and key information.
        """

        response = self.client.converse(
            modelId=self.model_id,
            messages=[{"role": "user", "content": [{"text": prompt}]}],
            inferenceConfig={"maxTokens": 2048, "temperature": 0.5}
        )

        return {
            "summary": response["output"]["message"]["content"][0]["text"],
            "usage": response.get("usage", {})
        }

    def extract_key_points(self, document: str) -> Dict:
        """
        Extract key points from document

        Args:
            document: Long text document

        Returns:
            List of key points
        """
        prompt = f"""
        Extract the key points from this document as a numbered list:

        Document:
        {document}

        Provide 5-7 key points that capture the main ideas.
        """

        response = self.client.converse(
            modelId=self.model_id,
            messages=[{"role": "user", "content": [{"text": prompt}]}],
            inferenceConfig={"maxTokens": 2048, "temperature": 0.3}
        )

        return {
            "key_points": response["output"]["message"]["content"][0]["text"],
            "usage": response.get("usage", {})
        }

    def answer_questions(self, document: str, questions: list) -> Dict:
        """
        Answer questions based on document content

        Args:
            document: Long text document
            questions: List of questions to answer

        Returns:
            Answers to questions
        """
        qa_prompt = f"""
        Based on the following document, answer these questions:

        Document:
        {document}

        Questions:
        {json.dumps(questions, indent=2)}

        Provide specific answers with references to the document where possible.
        """

        response = self.client.converse(
            modelId=self.model_id,
            messages=[{"role": "user", "content": [{"text": qa_prompt}]}],
            inferenceConfig={"maxTokens": 4096, "temperature": 0.3}
        )

        return {
            "answers": response["output"]["message"]["content"][0]["text"],
            "usage": response.get("usage", {})
        }

    def compare_documents(self, doc1: str, doc2: str) -> Dict:
        """
        Compare two documents and highlight differences

        Args:
            doc1: First document
            doc2: Second document

        Returns:
            Comparison analysis
        """
        prompt = f"""
        Compare these two documents and identify:
        1. Similarities
        2. Differences
        3. Which document is more comprehensive
        4. Key themes in each

        Document 1:
        {doc1}

        Document 2:
        {doc2}
        """

        response = self.client.converse(
            modelId=self.model_id,
            messages=[{"role": "user", "content": [{"text": prompt}]}],
            inferenceConfig={"maxTokens": 4096, "temperature": 0.3}
        )

        return {
            "comparison": response["output"]["message"]["content"][0]["text"],
            "usage": response.get("usage", {})
        }

# Example usage
if __name__ == "__main__":
    analyzer = LongContextAnalyzer()

    # Sample long document
    document = """
    Cloud computing has revolutionized the way businesses operate by providing on-demand access to computing resources over the internet. This paradigm shift enables organizations to scale their infrastructure dynamically, reducing the need for large upfront investments in hardware. Major cloud providers like AWS, Google Cloud, and Microsoft Azure offer a wide range of services including compute, storage, databases, and machine learning capabilities.

    The benefits of cloud computing include cost efficiency, as businesses only pay for what they use, and the ability to quickly deploy applications globally. Cloud services also provide enhanced security measures, automatic backups, and disaster recovery options that would be costly to implement on-premises. Additionally, the cloud enables collaboration by allowing teams to access shared resources from anywhere.

    However, cloud adoption also presents challenges such as data privacy concerns, potential vendor lock-in, and the need for skilled personnel to manage cloud environments. Organizations must carefully consider these factors when planning their cloud strategy. Hybrid cloud approaches, combining on-premises and cloud resources, have emerged as a popular solution to address some of these concerns.

    Serverless computing represents the next evolution of cloud services, abstracting away infrastructure management entirely. Developers can focus on writing code while the cloud provider handles scaling, load balancing, and resource allocation. This model is particularly beneficial for event-driven applications and microservices architectures.

    The future of cloud computing lies in edge computing, which brings processing closer to data sources to reduce latency, and increased integration with artificial intelligence and machine learning services. As technology continues to evolve, cloud computing will remain central to digital transformation initiatives across industries.
    """

    print("="*60)
    print("📄 Document Summary")
    print("="*60)
    summary = analyzer.summarize_document(document, 3)
    print(summary["summary"])
    print(f"\n💰 Tokens: {summary['usage']['inputTokens'] + summary['usage']['outputTokens']:,}")

    print("\n" + "="*60)
    print("🔑 Key Points Extraction")
    print("="*60)
    key_points = analyzer.extract_key_points(document)
    print(key_points["key_points"])
    print(f"\n💰 Tokens: {key_points['usage']['inputTokens'] + key_points['usage']['outputTokens']:,}")

    print("\n" + "="*60)
    print("❓ Question Answering")
    print("="*60)
    questions = [
        "What are the main benefits of cloud computing?",
        "What challenges does cloud adoption present?",
        "What is serverless computing?"
    ]
    qa = analyzer.answer_questions(document, questions)
    print(qa["answers"])
    print(f"\n💰 Tokens: {qa['usage']['inputTokens'] + qa['usage']['outputTokens']:,}")
