import "../../sass/vendor/nucleo/css/nucleo.css";
import "../../sass/vendor/font-awesome/css/font-awesome.css";

import globalComponents from "./globalComponents";
import globalDirectives from "./globalDirectives";
import VueLazyload from "vue-lazyload";

export default {
  install(Vue) {
    Vue.use(globalComponents);
    Vue.use(globalDirectives);
    Vue.use(VueLazyload);
  }
};
