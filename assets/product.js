import Vue from 'vue'
import App from './pages/product.vue'
import EasyUI from 'vx-easyui';

Vue.use(EasyUI);

new Vue({
    render: h => h(App)
}).$mount('#app')