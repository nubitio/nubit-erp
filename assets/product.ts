import {createApp} from "vue";
import App from "./components/product/ProductPage.vue";

window.addEventListener('load', () => {
    createApp(App).mount("#app");
});