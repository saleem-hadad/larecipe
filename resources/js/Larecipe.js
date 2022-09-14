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

    return this;
  }

  setup() {
    this.app.use(LaRecipeComponents);

    this.app.config.compilerOptions.delimiters = [{ replace: () => "(?!x)x" }];

    return this;
  }

  boot() {
      this.bootingCallbacks.forEach(callback => callback(this.app));

      this.bootingCallbacks = [];

      return this;
  }

  mount() {
    this.app.mount('#app');

    return this;
  }

  run() {
    this.init()
        .setup()
        .boot()
        .mount();
  }
}
