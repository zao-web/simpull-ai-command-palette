import React from 'react';
import { Command } from '../../types';

interface AIWorkflowProps {
    command: Command;
    onExecute: (plan: any) => void;
    onCancel: () => void;
}

const AIWorkflow: React.FC<AIWorkflowProps> = ({ command, onExecute, onCancel }) => {
    if (command.id !== 'ai-workflow' || !command.action?.plan) {
        return null;
    }

    const { plan } = command.action;
    const { summary, steps } = plan;

    return (
        <div className="aicp-ai-workflow-container">
            <h3>AI Proposed Workflow</h3>
            <p className="aicp-workflow-summary">{summary}</p>

            <h4>Steps:</h4>
            <ul className="aicp-workflow-steps">
                {steps.map((step: any, index: number) => (
                    <li key={index}>
                        <strong>{step.function}</strong>
                        <pre>{JSON.stringify(step.arguments, null, 2)}</pre>
                    </li>
                ))}
            </ul>

            <div className="aicp-workflow-actions">
                <button className="button button-primary" onClick={() => onExecute(plan)}>
                    Execute Plan
                </button>
                <button className="button" onClick={onCancel}>
                    Cancel
                </button>
            </div>
        </div>
    );
};

export default AIWorkflow;