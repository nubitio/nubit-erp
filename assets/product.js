import {createApp} from 'vue'
import App from './pages/product.vue'
import EasyUI from 'vx-easyui'

import 'vx-easyui/dist/themes/default/easyui.css'
import 'vx-easyui/dist/themes/icon.css'
import 'vx-easyui/dist/themes/vue.css'

createApp(App).use(EasyUI).mount('#app')