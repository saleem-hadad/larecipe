<template>
    <nav class="navbar has-bottom-border"
         :class="[
            {'navbar-expand-lg': expand},
            {[`navbar-${effect}`]: effect},
            {'navbar-transparent': transparent},
            {[`bg-${type}`]: type},
            {'rounded': round},
            {'fixed-top': fixed},
         ]">
        <div :class="[expand ? 'container-fluid pl-0 pr-0 pt-1 pb-1': 'container']">
            <slot name="container-pre"></slot>
            <slot name="brand">
                <a class="navbar-brand" href="#" @click.prevent="onTitleClick">
                    {{title}}
                </a>
            </slot>
            <larecipe-navbar-toggle-button :toggled="toggled"
                                  :target="contentId"
                                  @click.native.stop="toggled = !toggled">
            </larecipe-navbar-toggle-button>
            
            <slot name="container-after"></slot>

            <div class="collapse navbar-collapse" :class="{show: toggled}" :id="contentId">
                <div class="navbar-collapse-header">
                    <slot name="content-header" :close-menu="closeMenu"></slot>
                </div>
                <slot :close-menu="closeMenu"></slot>
            </div>
        </div>
    </nav>
</template>

<script>
import { FadeTransition } from "vue2-transitions"
import LarecipeNavbarToggleButton from "./LarecipeNavbarToggleButton";

export default {
  name: "larecipe-nav",
  components: {
    FadeTransition,
    LarecipeNavbarToggleButton
  },
  props: {
    type: {
      type: String,
      default: "primary",
      description: "Navbar type (e.g default, primary etc)"
    },
    title: {
      type: String,
      default: "",
      description: "Title of navbar"
    },
    contentId: {
      type: [String, Number],
      default: Math.random().toString(),
      description:
        "Explicit id for the menu. By default it's a generated random number"
    },
    effect: {
      type: String,
      default: "dark",
      description: "Effect of the navbar (light|dark)"
    },
    round: {
      type: Boolean,
      default: false,
      description: "Whether nav has rounded corners"
    },
    transparent: {
      type: Boolean,
      default: false,
      description: "Whether navbar is transparent"
    },
    expand: {
      type: Boolean,
      default: false,
      description: "Whether navbar should contain `navbar-expand-lg` class"
    },
    fixed: {
      type: Boolean,
      default: false,
      description: "Whether navbar should fixed top"
    }
  },
  data() {
    return {
      toggled: false
    };
  },
  methods: {
    onTitleClick(evt) {
      this.$emit("title-click", evt);
    },
    closeMenu() {
      this.toggled = false;
    }
  },
  mounted() {
    $("#search-button").click(() => {
      this.closeMenu()
    })
  }
};
</script>