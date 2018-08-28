<template>
    <fade-transition>
        <div class="alert" :class="[`alert-${type}`, {'alert-dismissible': dismissible}]" role="alert" v-if="visible">
            <slot v-if="!dismissible">
                <span v-if="icon" class="alert-inner--icon">
                    <i :class="icon"></i>
                </span>
                <span v-if="$slots.text" class="alert-inner--text">
                    <slot name="text"></slot>
                </span>
            </slot>
            <template v-else>
                <slot>
                    <span v-if="icon" class="alert-inner--icon">
                     <i :class="icon"></i>
                    </span>
                    <span v-if="$slots.text" class="alert-inner--text">
                    <slot name="text"></slot>
                </span>
                </slot>
                <slot name="dismiss-icon">
                    <button type="button" data-dismiss="alert" aria-label="Close" class="close" @click="dismissAlert">
                        <span aria-hidden="true">×</span>
                    </button>
                </slot>
            </template>
        </div>
    </fade-transition>
</template>
<script>
import { FadeTransition } from "vue2-transitions";

export default {
  name: "base-alert",
  components: {
    FadeTransition
  },
  props: {
    type: {
      type: String,
      default: "default",
      description: "Alert type"
    },
    icon: {
      type: String,
      default: "",
      description: "Alert icon. Will be overwritten by default slot"
    },
    dismissible: {
      type: Boolean,
      default: false,
      description: "Whether alert is closes when clicking"
    }
  },
  data() {
    return {
      visible: true
    };
  },
  methods: {
    dismissAlert() {
      this.visible = false;
    }
  }
};
</script>
