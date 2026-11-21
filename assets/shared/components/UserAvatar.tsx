import { useState } from 'react';
import { Avatar } from '@nubitio/ui';

interface UserAvatarProps {
  /** Shown as initials when there is no photo; the photo's alt text too. */
  name: string;
  avatarUrl?: string | null;
  size?: number;
  className?: string;
}

/**
 * A person's photo, or their initials when they have none (or it fails to load).
 * `@nubitio/ui`'s `Avatar` only knows initials, so the photo lives here in the ERP.
 */
export const UserAvatar = ({ name, avatarUrl, size = 32, className }: UserAvatarProps) => {
  // Remember which URL failed, so a new upload (new URL) gets a fresh try.
  const [failedUrl, setFailedUrl] = useState<string | null>(null);

  if (avatarUrl && avatarUrl !== failedUrl) {
    return (
      <img
        className={['erp-user-avatar', className].filter(Boolean).join(' ')}
        src={avatarUrl}
        alt={name}
        width={size}
        height={size}
        style={{ width: size, height: size }}
        onError={() => setFailedUrl(avatarUrl)}
      />
    );
  }

  return <Avatar owner={name} size={size} className={className} />;
};
