import React, { FC, useCallback, useState } from 'react';
import { Button, type ButtonTypes } from 'devextreme-react/button';
import { marked } from 'marked';
import HTMLReactParser from 'html-react-parser';

import { REGENERATION_TEXT } from './data';

function convertToHtml(value: string): string {
  return marked(value) as string;
}

interface MessageProps {
  text: string;
  onRegenerateButtonClick: ButtonTypes.Properties['onClick'];
}

const Message: FC<MessageProps> = ({ text, onRegenerateButtonClick }) => {
  const [icon, setIcon] = useState('copy');

  const onCopyButtonClick = useCallback(() => {
    navigator.clipboard?.writeText(text);
    setIcon('check');

    setTimeout(() => {
      setIcon('copy');
    }, 2500);
  }, [text]);

  if (text === REGENERATION_TEXT) {
    return <span>{REGENERATION_TEXT}</span>;
  }

  return (
    <React.Fragment>
      <div className="chat-messagebubble-text">{HTMLReactParser(convertToHtml(text))}</div>
      <div className="bubble-button-container">
        <Button icon={icon} stylingMode="text" hint="Copy" onClick={onCopyButtonClick} />
        <Button
          icon="refresh"
          stylingMode="text"
          hint="Regenerate"
          onClick={onRegenerateButtonClick}
        />
      </div>
    </React.Fragment>
  );
};

export default Message;
