import Vue from 'vue'
import App from './pages/product.vue'

import 'vx-easyui/dist/themes/bootstrap/easyui.css'
import 'vx-easyui/dist/themes/icon.css'
import 'vx-easyui/dist/themes/vue.css'
import EasyUI from 'vx-easyui';

Vue.use(EasyUI);

new Vue({
    render: h => h(App)
}).$mount('#app')