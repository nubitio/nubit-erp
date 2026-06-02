import { ContextMenu } from '@nubit/ui';

interface CardMenuItem {
  text: string;
  onClick?: () => void;
}

interface CardMenuProps {
  items: CardMenuItem[];
  visible?: boolean;
}

export const CardMenu = ({ items, visible = true }: CardMenuProps) => {
  return <ContextMenu visible={visible} items={items.map(({ text, onClick }) => ({ label: text, onClick }))} />;
};
