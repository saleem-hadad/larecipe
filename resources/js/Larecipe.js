import { createApp } from 'vue'
import LaRecipeComponents from "./components";

export default class LaRecipe {
  constructor() {
    this.bootingCallbacks = [];
  }

  booting(callback) {
    this.bootingCallbacks.push(callback);
  }

  init() {
    this.app = createApp();
  }
  setup() {
    this.app.use(LaRecipeComponents);

    const noDelimiter = { replace: () => "(?!x)x" };
    this.app.config.compilerOptions.delimiters = [noDelimiter, noDelimiter];
  }

  boot() {
      this.bootingCallbacks.forEach(callback => callback(this.app));

      this.bootingCallbacks = [];
  }

  run() {
    this.init();

    this.setup();

    this.boot();

    this.app.mount('#app');
  }
}
