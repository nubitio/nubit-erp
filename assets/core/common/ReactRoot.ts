import { createRoot } from 'react-dom/client';

const wrapper = document.getElementById('main-content') as HTMLElement;
const root = wrapper.appendChild(document.createElement('div'));
root.id = 'root';
export const reactRoot = createRoot(document.getElementById('root') as HTMLElement);
