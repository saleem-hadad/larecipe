require('./bootsrap');

import Vue from "vue";
import Argon from "./plugins/argon-kit";
import BaseNav from "./components/BaseNav";
import BaseDropdown from "./components/BaseDropdown";
import CloseButton from "./components/CloseButton";

Vue.config.productionTip = false;
Vue.use(Argon);

const app = new Vue({
  el: '#app',
  components: {
    BaseNav,
    BaseDropdown,
    CloseButton
  }
});