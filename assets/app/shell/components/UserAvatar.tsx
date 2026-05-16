import React from 'react';
import './UserAvatar.scss';

const USER_AVATAR_COLORS = [
  '#4a6fa5', '#3d8a74', '#7a6099', '#4a8ab5',
  '#6a7f5e', '#a06050', '#526a7d', '#7d7040',
];

export const getUserAvatarInitials = (owner = ''): string => {
  const normalizedOwner = owner.trim();
  if (!normalizedOwner) {
    return 'NA';
  }

  const words = normalizedOwner.split(/\s+/);
  if (words.length >= 2) {
    return `${words[0][0]}${words[1][0]}`.toUpperCase();
  }

  return normalizedOwner.slice(0, 2).toUpperCase();
};

export const getUserAvatarColor = (owner = ''): string => {
  let hash = 0;
  for (let index = 0; index < owner.length; index++) {
    hash = (hash * 31 + owner.charCodeAt(index)) & 0xfffffff;
  }

  return USER_AVATAR_COLORS[Math.abs(hash) % USER_AVATAR_COLORS.length];
};

export const createUserAvatarDataUri = (owner = ''): string => {
  const initials = getUserAvatarInitials(owner);
  const backgroundColor = getUserAvatarColor(owner);
  const svg = `<svg xmlns='http://www.w3.org/2000/svg' width='64' height='64' viewBox='0 0 64 64'><circle cx='32' cy='32' r='32' fill='${backgroundColor}'/><text x='32' y='32' text-anchor='middle' dominant-baseline='middle' font-family='Ubuntu, Roboto, Arial, sans-serif' font-size='24' font-weight='700' fill='rgba(255,255,255,0.92)'>${initials}</text></svg>`;

  return `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`;
};

type UserAvatarProps = {
  owner?: string;
  size?: number;
};

export const UserAvatar = ({ owner = '', size = 24 }: UserAvatarProps) => (
  <div
    className="circle"
    style={{
      width: `${size}px`,
      height: `${size}px`,
      lineHeight: `${size}px`,
      background: getUserAvatarColor(owner),
    }}
  >
    {getUserAvatarInitials(owner)}
  </div>
);
