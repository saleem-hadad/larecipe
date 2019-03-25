<template>
  <div class="search-box" v-click-outside="close">
    <larecipe-input input-classes="algolia-search-input has-text-centered" placeholder="Search"></larecipe-input>
  </div>
</template>

<script>
require("docsearch.js/dist/cdn/docsearch.min.css");
import docsearch from "docsearch.js/dist/cdn/docsearch.min.js";

export default {
  name: "algolia-search-box",
  props: ["algoliaKey", "algoliaIndex", "version"],
  methods: {
    close() {
      this.$emit("close");
    }
  },
  mounted() {
    docsearch({
      apiKey: this.algoliaKey,
      indexName: this.algoliaIndex,
      inputSelector: ".algolia-search-input",
      algoliaOptions: { facetFilters: ["version:" + this.version] },
      debug: false
    });

    $(".algolia-search-input").focus();
  }
};
</script>