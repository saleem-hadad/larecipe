import ExampleComponent from './ExampleComponent';

LaRecipe.booting((Vue) => {
    Vue.component(ExampleComponent.name, ExampleComponent);
})