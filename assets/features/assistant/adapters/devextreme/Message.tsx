import React, { FC, useCallback, useState } from 'react';
import { marked } from 'marked';
import HTMLReactParser from 'html-react-parser';

import { REGENERATION_TEXT } from './data';

function convertToHtml(value: string): string {
  return marked(value) as string;
}

interface MessageProps {
  text: string;
  onRegenerateButtonClick: React.MouseEventHandler<HTMLButtonElement>;
}

const Message: FC<MessageProps> = ({ text, onRegenerateButtonClick }) => {
  const [copied, setCopied] = useState(false);

  const onCopyButtonClick = useCallback(() => {
    navigator.clipboard?.writeText(text);
    setCopied(true);

    setTimeout(() => {
      setCopied(false);
    }, 2500);
  }, [text]);

  if (text === REGENERATION_TEXT) {
    return <span>{REGENERATION_TEXT}</span>;
  }

  return (
    <React.Fragment>
      <div className="chat-messagebubble-text">{HTMLReactParser(convertToHtml(text))}</div>
      <div className="bubble-button-container">
        <button
          type="button"
          className="app-icon-button"
          title="Copy"
          onClick={onCopyButtonClick}
          aria-label="Copy"
        >
          <i className={`ph ph-${copied ? 'check' : 'copy'}`} aria-hidden="true" />
        </button>
        <button
          type="button"
          className="app-icon-button"
          title="Regenerate"
          onClick={onRegenerateButtonClick}
          aria-label="Regenerate"
        >
          <i className="ph ph-arrow-clockwise" aria-hidden="true" />
        </button>
      </div>
    </React.Fragment>
  );
};

export default Message;
