<template>
  <div id="backtotop">
    <a href="#"></a>
  </div>
</template>

<script>
export default {
  name: "larecipe-back-to-top",
  mounted() {
    $(window).on("scroll", function() {
      if ($(window).scrollTop() >= 300) {
        $("#backtotop").addClass("visible");
      } else {
        $("#backtotop").removeClass("visible");
      }
    });
    $("#backtotop a").on("click", function() {
      $("html, body").animate(
        {
          scrollTop: 0
        },
        500
      );
      return false;
    });
  }
};
</script>