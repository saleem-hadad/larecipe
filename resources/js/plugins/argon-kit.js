import globalComponents from "./globalComponents";
import VueLazyload from "vue-lazyload";

export default {
  install(Vue) {
    Vue.use(globalComponents);
    Vue.use(VueLazyload);
  }
};
