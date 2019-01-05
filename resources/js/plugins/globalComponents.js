import AlgoliaSearchBox from "../components/AlgoliaSearchBox";
import InternalSearchBox from "../components/InternalSearchBox";
import LarecipeAlert from "../components/LarecipeAlert";
import LarecipeBackToTop from "../components/LarecipeBackToTop";
import LarecipeBadge from "../components/LarecipeBadge";
import LarecipeButton from "../components/LarecipeButton";
import LarecipeCard from "../components/LarecipeCard";
import LarecipeCheckbox from "../components/LarecipeCheckbox";
import LarecipeCloseButton from "../components/LarecipeCloseButton";
import LarecipeDropdown from "../components/LarecipeDropdown";
import LarecipeIcon from "../components/LarecipeIcon";
import LarecipeInput from "../components/LarecipeInput";
import LarecipeNav from "../components/LarecipeNav";
import LarecipeProgress from "../components/LarecipeProgress";
import LarecipeSwitch from "../components/LarecipeSwitch";

export default {
  install(Vue) {
    Vue.component(AlgoliaSearchBox.name, AlgoliaSearchBox);
    Vue.component(InternalSearchBox.name, InternalSearchBox);
    Vue.component(LarecipeAlert.name, LarecipeAlert);
    Vue.component(LarecipeBackToTop.name, LarecipeBackToTop);
    Vue.component(LarecipeBadge.name, LarecipeBadge);
    Vue.component(LarecipeButton.name, LarecipeButton);
    Vue.component(LarecipeCard.name, LarecipeCard);
    Vue.component(LarecipeCheckbox.name, LarecipeCheckbox);
    Vue.component(LarecipeCloseButton.name, LarecipeCloseButton);
    Vue.component(LarecipeDropdown.name, LarecipeDropdown);
    Vue.component(LarecipeIcon.name, LarecipeIcon);
    Vue.component(LarecipeInput.name, LarecipeInput);
    Vue.component(LarecipeNav.name, LarecipeNav);
    Vue.component(LarecipeProgress.name, LarecipeProgress);
    Vue.component(LarecipeSwitch.name, LarecipeSwitch);
  }
};
