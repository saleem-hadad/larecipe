<template>
  <div id="backtotop" class="fixed bottom-4 right-4 opacity-0 cursor-pointer z-50 rounded-full bg-primary h-12 w-12 -mb-20 flex items-center justify-center transition-all duration-300 ease-in-out">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" viewBox="0 0 20 20" fill="currentColor">
      <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
    </svg>
  </div>
</template>

<script>
export default {
  name: "larecipe-back-to-top",
  mounted() {
    var backToTop = document.getElementById("backtotop");

    window.onscroll = () => {
      if (document.body.scrollTop > 300 || document.documentElement.scrollTop > 300) {
        backToTop.classList.remove("opacity-0");
        backToTop.classList.remove("-mb-20");
      } else {
        backToTop.classList.add("opacity-0");
        backToTop.classList.add("-mb-20");
      }
    };

    backToTop.addEventListener('click', () => {
      window.scrollTo({top: 0, behavior: 'smooth'});
    });
  }
};
</script>