import Button from 'devextreme-react/button';
import React, { useEffect, useState } from 'react';

import './NotificationPanel.scss';
import { useNavigate } from 'react-router-dom';
import { httpClient } from '../../../core/http/HttpClient';
import { Constants } from '../../../core/common/Constants';

interface NotificationPanelProps {
  visible: boolean;
  onHiding: () => void;
}

const NotificationPanel = (props: NotificationPanelProps) => {
  const navigate = useNavigate();
  const [messages, setMessages] = useState<any[]>([]);

  const showAll = () => {
    navigate('/message');
    props.onHiding();
  };

  useEffect(() => {
    httpClient.get<any>(Constants.API_BASE_URL + 'messages').subscribe((response) => {
      setMessages(response.data);
    });
  }, []);

  return (
    <>
      <div className="notification-header">
        <div className="notification-header-title">Notificaciones</div>
        <div className="notification-header-button">
          <Button icon="close" stylingMode="text" onClick={props.onHiding} />
        </div>
      </div>
      <div className="notification-body">
        {messages.map((item, index) => (
          <div key={index} className="notification-body-item">
            <div className="notification-body-item-icon">
              <Button icon="check" stylingMode="text" />
            </div>
            <div className="notification-body-item-title">
              <div className="notification-body-item-title-text">{item.message}</div>
              <div className="notification-body-item-title-time">5 minutes ago</div>
            </div>
          </div>
        ))}
      </div>
      <div className="notification-footer">
        <Button text="Ver todo" stylingMode="text" onClick={showAll} />
      </div>
    </>
  );
};

export default NotificationPanel;
