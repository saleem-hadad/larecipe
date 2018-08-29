import LarecipeAlert from "../components/LarecipeAlert";
import LarecipeBadge from "../components/LarecipeBadge";
import LarecipeButton from "../components/LarecipeButton";
import LarecipeCard from "../components/LarecipeCard";
import LarecipeCheckbox from "../components/LarecipeCheckbox";
import LarecipeCloseButton from "../components/LarecipeCloseButton";
import LarecipeDropdown from "../components/LarecipeDropdown";
import LarecipeIcon from "../components/LarecipeIcon";
import LarecipeInput from "../components/LarecipeInput";
import LarecipeModal from "../components/LarecipeModal";
import LarecipeNav from "../components/LarecipeNav";
import LarecipePagination from "../components/LarecipePagination";
import LarecipeProgress from "../components/LarecipeProgress";
import LarecipeRadio from "../components/LarecipeRadio";
import LarecipeSlider from "../components/LarecipeSlider";
import LarecipeSwitch from "../components/LarecipeSwitch";

export default {
  install(Vue) {
    Vue.component(LarecipeAlert.name, LarecipeAlert);
    Vue.component(LarecipeBadge.name, LarecipeBadge);
    Vue.component(LarecipeButton.name, LarecipeButton);
    Vue.component(LarecipeCard.name, LarecipeCard);
    Vue.component(LarecipeCheckbox.name, LarecipeCheckbox);
    Vue.component(LarecipeCloseButton.name, LarecipeCloseButton);
    Vue.component(LarecipeDropdown.name, LarecipeDropdown);
    Vue.component(LarecipeIcon.name, LarecipeIcon);
    Vue.component(LarecipeInput.name, LarecipeInput);
    Vue.component(LarecipeModal.name, LarecipeModal);
    Vue.component(LarecipeNav.name, LarecipeNav);
    Vue.component(LarecipePagination.name, LarecipePagination);
    Vue.component(LarecipeProgress.name, LarecipeProgress);
    Vue.component(LarecipeRadio.name, LarecipeRadio);
    Vue.component(LarecipeSlider.name, LarecipeSlider);
    Vue.component(LarecipeSwitch.name, LarecipeSwitch);
  }
};
