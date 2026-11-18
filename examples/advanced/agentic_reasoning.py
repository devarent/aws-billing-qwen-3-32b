"""
Agentic Reasoning with Qwen3-Next on Amazon Bedrock
Demonstrates multi-step reasoning with complex tool orchestration
"""

import boto3
import json
import time
from typing import Dict, List, Any

class QwenAgent:
    """Agent for multi-step reasoning with Qwen3-Next"""

    def __init__(self, region="us-east-1"):
        self.client = boto3.client("bedrock-runtime", region_name=region)
        self.model_id = "qwen.qwen3-next-80b-a3b"
        self.conversation_history = []

    def converse(self, message: str, tools: List[Dict] = None) -> Dict:
        """
        Send message to Qwen3-Next with optional tool definitions

        Args:
            message: User message
            tools: List of tool definitions for function calling

        Returns:
            Response with content and tool calls
        """
        messages = [{"role": "user", "content": [{"text": message}]}]

        inference_config = {
            "maxTokens": 4096,
            "temperature": 0.7,
            "topP": 0.9
        }

        kwargs = {
            "modelId": self.model_id,
            "messages": messages,
            "inferenceConfig": inference_config
        }

        if tools:
            kwargs["toolConfig"] = {"tools": tools}

        response = self.client.converse(**kwargs)

        result = {
            "content": response["output"]["message"]["content"][0]["text"],
            "usage": response.get("usage", {}),
            "tool_calls": response.get("output", {}).get("message", {}).get("content", [])
        }

        self.conversation_history.append({"role": "user", "content": message})
        self.conversation_history.append({"role": "assistant", "content": result["content"]})

        return result

    def multi_step_reasoning(self, task: str) -> Dict:
        """
        Execute multi-step reasoning task

        Args:
            task: Complex task description

        Returns:
            Final answer with reasoning steps
        """
        print(f"\n🎯 Task: {task}\n")

        # Step 1: Break down the task
        breakdown_prompt = f"""
        Break down this task into logical steps: {task}

        Provide a numbered list of steps needed to complete this task.
        Focus on clarity and logical progression.
        """

        breakdown = self.converse(breakdown_prompt)
        print("📋 Task Breakdown:")
        print(breakdown["content"])
        print("\n" + "="*60 + "\n")

        # Step 2: Execute each step
        steps = self._extract_steps(breakdown["content"])
        results = []

        for i, step in enumerate(steps, 1):
            print(f"⚙️  Executing Step {i}: {step}")
            step_result = self.converse(f"Execute this step: {step}")
            results.append({
                "step": step,
                "result": step_result["content"],
                "tokens": step_result["usage"]
            })
            print(f"✅ Step {i} completed\n")

        # Step 3: Synthesize final answer
        synthesis_prompt = f"""
        Based on the following steps and results, provide a comprehensive final answer:

        Task: {task}

        Steps and Results:
        {json.dumps(results, indent=2)}

        Provide a clear, well-structured final answer.
        """

        final_answer = self.converse(synthesis_prompt)

        return {
            "task": task,
            "breakdown": breakdown["content"],
            "steps": results,
            "final_answer": final_answer["content"],
            "total_tokens": breakdown["usage"]["inputTokens"] + breakdown["usage"]["outputTokens"] +
                           sum(r["tokens"]["inputTokens"] + r["tokens"]["outputTokens"] for r in results) +
                           final_answer["usage"]["inputTokens"] + final_answer["usage"]["outputTokens"]
        }

    def _extract_steps(self, text: str) -> List[str]:
        """Extract numbered steps from text"""
        lines = text.split('\n')
        steps = []
        for line in lines:
            if line.strip() and any(line.strip().startswith(str(i) + '.') for i in range(1, 20)):
                steps.append(line.strip())
        return steps

# Example usage
if __name__ == "__main__":
    agent = QwenAgent()

    # Example 1: Complex planning task
    task1 = "Design a cost-effective architecture for a startup web application that needs to handle 1000 concurrent users with 99.9% uptime"

    print("="*60)
    print("Example 1: Architecture Planning")
    print("="*60)
    result1 = agent.multi_step_reasoning(task1)

    print("\n" + "="*60)
    print("📊 Final Answer:")
    print("="*60)
    print(result1["final_answer"])
    print(f"\n💰 Total Tokens Used: {result1['total_tokens']:,}")

    # Example 2: Problem-solving task
    task2 = "Debug why a Python application is experiencing memory leaks after running for 24 hours"

    print("\n" + "="*60)
    print("Example 2: Debugging Strategy")
    print("="*60)
    result2 = agent.multi_step_reasoning(task2)

    print("\n" + "="*60)
    print("📊 Final Answer:")
    print("="*60)
    print(result2["final_answer"])
    print(f"\n💰 Total Tokens Used: {result2['total_tokens']:,}")
