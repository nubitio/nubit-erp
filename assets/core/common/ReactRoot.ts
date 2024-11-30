import {createRoot} from "react-dom/client";

let wrapper = document.getElementById('main-content') as HTMLElement;
let root = wrapper.appendChild(document.createElement('div'));
root.id = 'root';
export const reactRoot = createRoot(document.getElementById("root") as HTMLElement);