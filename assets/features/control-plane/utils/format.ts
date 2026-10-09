export const formatBytes = (bytes: number): string => {
  if (bytes >= 1073741824) return `${(bytes / 1073741824).toFixed(2)} GB`;
  if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1)} MB`;
  if (bytes >= 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${bytes} B`;
};

export const formatResourceLabel = (key: string): string => {
  if (key === 'storage_bytes') return 'DB Storage';
  return key.replace(/_/g, ' ');
};
