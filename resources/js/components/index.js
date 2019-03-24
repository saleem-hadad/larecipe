import AlgoliaSearchBox from "./AlgoliaSearchBox";
import InternalSearchBox from "./InternalSearchBox";
import LarecipeBackToTop from "./LarecipeBackToTop";
import LarecipeBadge from "./LarecipeBadge";
import LarecipeButton from "./LarecipeButton";
import LarecipeCard from "./LarecipeCard";
import LarecipeInput from "./LarecipeInput";
import LarecipeProgress from "./LarecipeProgress";

export default {
  install(Vue) {
    Vue.component(AlgoliaSearchBox.name, AlgoliaSearchBox);
    Vue.component(InternalSearchBox.name, InternalSearchBox);
    Vue.component(LarecipeBackToTop.name, LarecipeBackToTop);
    Vue.component(LarecipeBadge.name, LarecipeBadge);
    Vue.component(LarecipeButton.name, LarecipeButton);
    Vue.component(LarecipeCard.name, LarecipeCard);
    Vue.component(LarecipeInput.name, LarecipeInput);
    Vue.component(LarecipeProgress.name, LarecipeProgress);
  }
};
