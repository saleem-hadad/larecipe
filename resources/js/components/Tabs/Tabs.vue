<template>
  <component :is="layoutComponent">
    <template slot="nav">
      <div class="nav-wrapper">
        <ul class="nav"
            role="tablist"
            :class="
            [type ? `nav-pills-${type}`: '',
              pills ? 'nav-pills': 'nav-tabs',
             {'nav-pills-icons': icons},
             {'nav-fill': fill},
             {'nav-pills-circle': circle},
             {'justify-content-center': centered},
             tabNavClasses
            ]">

          <li v-for="tab in tabs"
              class="nav-item"
              :key="tab.id || tab.title">

            <a data-toggle="tab"
               role="tab"
               class="nav-link"
               :href="`#${tab.id || tab.title}`"
               @click.prevent="activateTab(tab)"
               :aria-selected="tab.active"
               :class="{active: tab.active}">
              <tab-item-content :tab="tab">
              </tab-item-content>
            </a>

          </li>

        </ul>
      </div>
    </template>
    <div slot="content" class="tab-content"
         :class="[tabContentClasses]">
      <slot v-bind="slotData"></slot>
    </div>
  </component>
</template>

<script>
import PillsLayout from "./PillsLayout";
import TabsLayout from "./TabsLayout";
export default {
  name: "tabs",
  components: {
    TabsLayout,
    PillsLayout,
    TabItemContent: {
      props: ["tab"],
      render(h) {
        return h("div", [this.tab.$slots.title || this.tab.title]);
      }
    }
  },
  props: {
    type: {
      type: String,
      default: "",
      validator: value => {
        let acceptedValues = [
          "",
          "primary",
          "info",
          "success",
          "warning",
          "danger"
        ];
        return acceptedValues.indexOf(value) !== -1;
      },
      description: "Tabs type (primary|info|danger|default|warning|success)"
    },
    pills: {
      type: Boolean,
      default: true,
      description: "Whether tabs are pills"
    },
    circle: {
      type: Boolean,
      default: false,
      description: "Whether tabs are circle"
    },
    fill: {
      type: Boolean,
      default: true,
      description: "Whether to fill each tab"
    },
    activeTab: {
      type: String,
      default: "",
      description: "Default active tab name"
    },
    tabNavWrapperClasses: {
      type: [String, Object],
      default: "",
      description: "Tab Nav wrapper (div) css classes"
    },
    tabNavClasses: {
      type: [String, Object],
      default: "",
      description: "Tab Nav (ul) css classes"
    },
    tabContentClasses: {
      type: [String, Object],
      default: "",
      description: "Tab content css classes"
    },
    icons: {
      type: Boolean,
      description: "Whether tabs should be of icon type (small no text)"
    },
    centered: {
      type: Boolean,
      description: "Whether tabs are centered"
    },
    value: {
      type: String,
      description: "Initial value (active tab)"
    }
  },
  provide() {
    return {
      addTab: this.addTab,
      removeTab: this.removeTab
    };
  },
  data() {
    return {
      tabs: [],
      activeTabIndex: 0
    };
  },
  computed: {
    layoutComponent() {
      return this.pills ? "pills-layout" : "tabs-layout";
    },
    slotData() {
      return {
        activeTabIndex: this.activeTabIndex,
        tabs: this.tabs
      };
    }
  },
  methods: {
    findAndActivateTab(title) {
      let tabToActivate = this.tabs.find(t => t.title === title);
      if (tabToActivate) {
        this.activateTab(tabToActivate);
      }
    },
    activateTab(tab) {
      if (this.handleClick) {
        this.handleClick(tab);
      }
      this.deactivateTabs();
      tab.active = true;
      this.activeTabIndex = this.tabs.findIndex(t => t.active);
    },
    deactivateTabs() {
      this.tabs.forEach(tab => {
        tab.active = false;
      });
    },
    addTab(tab) {
      if (this.activeTab === tab.name) {
        tab.active = true;
      }
      this.tabs.push(tab);
    },
    removeTab(tab) {
      const tabs = this.tabs;
      const index = tabs.indexOf(tab);
      if (index > -1) {
        tabs.splice(index, 1);
      }
    }
  },
  mounted() {
    this.$nextTick(() => {
      if (this.value) {
        this.findAndActivateTab(this.value);
      } else {
        if (this.tabs.length > 0) {
          this.activateTab(this.tabs[0]);
        }
      }
    });
  },
  watch: {
    value(newVal) {
      this.findAndActivateTab(newVal);
    }
  }
};
</script>
