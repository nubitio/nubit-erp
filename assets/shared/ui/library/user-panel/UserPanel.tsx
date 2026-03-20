import React, { useCallback, useMemo, useRef } from 'react';
import DropDownButton from 'devextreme-react/drop-down-button';
import { Template } from 'devextreme-react/core/template';
import { UserMenuSection } from '../user-menu-section/UserMenuSection';
import type { UserPanelProps } from '@assets/types';
import { useAuth } from '@app/contexts/auth';
import { ListRef } from 'devextreme-react/list';
import './UserPanel.scss';
import { createUserAvatarDataUri } from '../user-avatar/UserAvatar';

export const UserPanel = ({ menuMode }: UserPanelProps) => {
  const { user } = useAuth();
  const listRef = useRef<ListRef>(null);
  const avatarIcon = useMemo(() => createUserAvatarDataUri(user?.username ?? ''), [user?.username]);

  const dropDownButtonAttributes = {
    class: 'user-button',
  };

  const buttonDropDownOptions = {
    width: '200',
  };

  const dropDownButtonContentReady = useCallback(
    ({
      component,
    }: {
      component: { registerKeyHandler: (key: string, handler: () => void) => void };
    }) => {
      component.registerKeyHandler('downArrow', () => {
        listRef.current?.instance().focus();
      });
    },
    [],
  );

  return (
    <div className="user-panel">
      {menuMode === 'context' && (
        <DropDownButton
          stylingMode="text"
          icon={avatarIcon}
          showArrowIcon={false}
          elementAttr={dropDownButtonAttributes}
          dropDownOptions={buttonDropDownOptions}
          dropDownContentTemplate="dropDownTemplate"
          onContentReady={dropDownButtonContentReady}
        >
          <Template name="dropDownTemplate">
            <UserMenuSection listRef={listRef} />
          </Template>
        </DropDownButton>
      )}
      {menuMode === 'list' && <UserMenuSection showAvatar />}
    </div>
  );
};
