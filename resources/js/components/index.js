import ClickOutside from './click-outside';

import AlgoliaSearchBox from "./AlgoliaSearchBox";
import InternalSearchBox from "./InternalSearchBox";
import LarecipeBackToTop from "./LarecipeBackToTop";
import LarecipeBadge from "./LarecipeBadge";
import LarecipeButton from "./LarecipeButton";
import LarecipeCard from "./LarecipeCard";
import LarecipeDropdown from "./LarecipeDropdown";
import LarecipeProgress from "./LarecipeProgress";

export default {
  install(Vue) {
    Vue.directive("click-outside", ClickOutside);

    Vue.component(AlgoliaSearchBox.name, AlgoliaSearchBox);
    Vue.component(InternalSearchBox.name, InternalSearchBox);
    Vue.component(LarecipeBackToTop.name, LarecipeBackToTop);
    Vue.component(LarecipeBadge.name, LarecipeBadge);
    Vue.component(LarecipeButton.name, LarecipeButton);
    Vue.component(LarecipeCard.name, LarecipeCard);
    Vue.component(LarecipeDropdown.name, LarecipeDropdown);
    Vue.component(LarecipeProgress.name, LarecipeProgress);
  }
};
