import { useRef, useState } from 'react';

export const useSalePrintPreview = () => {
  const selectedSaleIdRef = useRef<string | undefined>(undefined);
  const [isPreviewVisible, setIsPreviewVisible] = useState(false);

  const openPreview = (saleId: string) => {
    selectedSaleIdRef.current = saleId;
    setIsPreviewVisible(true);
  };

  const closePreview = () => {
    setIsPreviewVisible(false);
  };

  const confirmPreview = () => {
    setIsPreviewVisible(false);
    window.open(`/receipt/${selectedSaleIdRef.current}/ticket`, '_blank');
  };

  return {
    selectedSaleIdRef,
    isPreviewVisible,
    openPreview,
    closePreview,
    confirmPreview,
  };
};
