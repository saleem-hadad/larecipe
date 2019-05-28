import "../sass/vendor/nucleo/css/nucleo.css";

import Vue from "vue";
import mediumZoom from "medium-zoom";
import LaRecipeComponents from "./components";

Vue.use(LaRecipeComponents);
Vue.config.productionTip = false;
const noDelimiter = { replace: () => "(?!x)x" };

export default class LaRecipe {
  constructor(config) {
    this.config = config;
    this.bootingCallbacks = [];
  }

  booting(callback) {
    this.bootingCallbacks.push(callback);
  }

  boot() {
      this.bootingCallbacks.forEach(callback => callback(Vue));

      this.bootingCallbacks = [];
  }

  run() {
    this.boot();

    this.app = new Vue({
      el: "#app",
      delimiters: [noDelimiter, noDelimiter],
      data() {
        return {
          sidebar: false,
          searchBox: false
        };
      },
      watch: {
        // listen to the sidebar value if changed => if so, cache it.
        sidebar: function() {
          localStorage.setItem("larecipeSidebar", this.sidebar);
        }
      },
      mounted() {
        this.handleSidebarVisibility();
        this.addLinksToHeaders();
        this.setupSmoothScrolling();
        this.activateCurrentSection();
        this.parseDocsContent();
        this.setupKeyboardShortcuts();
        mediumZoom(".documentation img");
      },
      methods: {
        handleSidebarVisibility() {
          // check if the user already save some sidebar's visibility preferenece
          // if yes, load it from the cache.
          if (
            typeof Storage !== "undefined" &&
            localStorage.getItem("larecipeSidebar") !== null
          ) {
            this.sidebar = localStorage.getItem("larecipeSidebar") == "true";
          }

          // hide the sidebar if clicked on the page on mobile screens
          $(".documentation").click(() => {
            if (window.matchMedia("(max-width: 960px)").matches) {
              this.sidebar = false;
            }
          });
        },
        addLinksToHeaders() {
          $(".documentation")
            .find("a[name]")
            .each(function() {
              var anchor = $('<a href="#' + this.name + '"/>');
              $(this)
                .parent()
                .next("h2")
                .wrapInner(anchor);
            });
        },
        setupSmoothScrolling() {
          $(
            '.documentation > ul:first > li a[href*="#"]:not([href="#"])'
          ).click(function() {
            var target = $(this.hash);
            target = target.length
              ? target
              : $("[name=" + this.hash.slice(1) + "]");
            if (target.length) {
              $("html,body").animate(
                {
                  scrollTop: target.offset().top - 70
                },
                500
              );
            }
          });
        },
        activateCurrentSection() {
          // active sidebar element
          if ($(".sidebar ul").length) {
            var current = $(".sidebar ul").find(
              'li a[href="' + window.location.pathname + '"]'
            );

            if (current.length) {
              current
                .parent()
                .css("font-weight", "bold")
                .addClass("is-active");
            }
          }
        },
        parseDocsContent() {
          // custom blockquote icons
          $(".documentation blockquote p:first-child").each(function() {
            var str = $(this).html();
            var match = str.match(/\{(.*?)\}/);

            if (match) {
              var icon = match[1] || false;
            }

            if (icon) {
              var word = icon;

              if (
                icon.indexOf(".") >= 0 &&
                icon.split(".")[1].startsWith("fa")
              ) {
                var match = icon.split(".");
                var word = match[0];
                icon = '<i class="fa ' + match[1] + ' fa-4x"></i>';
              } else {
                var icons = {
                  info:
                    '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:a="http://ns.adobe.com/AdobeSVGViewerExtensions/3.0/" version="1.1" x="0px" y="0px" width="90px" height="90px" viewBox="0 0 90 90" enable-background="new 0 0 90 90" xml:space="preserve"><path fill="#FFFFFF" d="M45 0C20.1 0 0 20.1 0 45s20.1 45 45 45 45-20.1 45-45S69.9 0 45 0zM45 74.5c-3.6 0-6.5-2.9-6.5-6.5s2.9-6.5 6.5-6.5 6.5 2.9 6.5 6.5S48.6 74.5 45 74.5zM52.1 23.9l-2.5 29.6c0 2.5-2.1 4.6-4.6 4.6 -2.5 0-4.6-2.1-4.6-4.6l-2.5-29.6c-0.1-0.4-0.1-0.7-0.1-1.1 0-4 3.2-7.2 7.2-7.2 4 0 7.2 3.2 7.2 7.2C52.2 23.1 52.2 23.5 52.1 23.9z"/></svg>',
                  primary:
                    '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:a="http://ns.adobe.com/AdobeSVGViewerExtensions/3.0/" version="1.1" x="0px" y="0px" width="56.6px" height="87.5px" viewBox="0 0 56.6 87.5" enable-background="new 0 0 56.6 87.5" xml:space="preserve"><path fill="#FFFFFF" d="M28.7 64.5c-1.4 0-2.5-1.1-2.5-2.5v-5.7 -5V41c0-1.4 1.1-2.5 2.5-2.5s2.5 1.1 2.5 2.5v10.1 5 5.8C31.2 63.4 30.1 64.5 28.7 64.5zM26.4 0.1C11.9 1 0.3 13.1 0 27.7c-0.1 7.9 3 15.2 8.2 20.4 0.5 0.5 0.8 1 1 1.7l3.1 13.1c0.3 1.1 1.3 1.9 2.4 1.9 0.3 0 0.7-0.1 1.1-0.2 1.1-0.5 1.6-1.8 1.4-3l-2-8.4 -0.4-1.8c-0.7-2.9-2-5.7-4-8 -1-1.2-2-2.5-2.7-3.9C5.8 35.3 4.7 30.3 5.4 25 6.7 14.5 15.2 6.3 25.6 5.1c13.9-1.5 25.8 9.4 25.8 23 0 4.1-1.1 7.9-2.9 11.2 -0.8 1.4-1.7 2.7-2.7 3.9 -2 2.3-3.3 5-4 8L41.4 53l-2 8.4c-0.3 1.2 0.3 2.5 1.4 3 0.3 0.2 0.7 0.2 1.1 0.2 1.1 0 2.2-0.8 2.4-1.9l3.1-13.1c0.2-0.6 0.5-1.2 1-1.7 5-5.1 8.2-12.1 8.2-19.8C56.4 12 42.8-1 26.4 0.1zM43.7 69.6c0 0.5-0.1 0.9-0.3 1.3 -0.4 0.8-0.7 1.6-0.9 2.5 -0.7 3-2 8.6-2 8.6 -1.3 3.2-4.4 5.5-7.9 5.5h-4.1H28h-0.5 -3.6c-3.5 0-6.7-2.4-7.9-5.7l-0.1-0.4 -1.8-7.8c-0.4-1.1-0.8-2.1-1.2-3.1 -0.1-0.3-0.2-0.5-0.2-0.9 0.1-1.3 1.3-2.1 2.6-2.1H41C42.4 67.5 43.6 68.2 43.7 69.6zM37.7 72.5H26.9c-4.2 0-7.2 3.9-6.3 7.9 0.6 1.3 1.8 2.1 3.2 2.1h4.1 0.5 0.5 3.6c1.4 0 2.7-0.8 3.2-2.1L37.7 72.5z"/></svg>',
                  success:
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 25.625 25.625"><g transform="translate(-0.188 -0.188)"><path d="M13,.188A12.813,12.813,0,1,0,25.813,13,12.815,12.815,0,0,0,13,.188Zm6.734,8.848L12.863,19.168a1.076,1.076,0,0,1-.848.5,1.378,1.378,0,0,1-.9-.4L7.086,15.238a.707.707,0,0,1,0-1l1-1a.7.7,0,0,1,.992,0L11.7,15.867l5.7-8.414a.712.712,0,0,1,.98-.187l1.168.793A.706.706,0,0,1,19.734,9.035Z"/></g></svg>',
                  danger:
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50"><path d="M25,0C15.625,0,8,5.977,8,13.313a10.656,10.656,0,0,0,1.5,5.781,10.92,10.92,0,0,1,1.438,4.969A10.908,10.908,0,0,0,13,18.406a25.849,25.849,0,0,0-.969-6.125,1.009,1.009,0,1,1,1.938-.562A27.747,27.747,0,0,1,15,18.406c0,2.887-1.4,5.184-2.531,7.031-.395.645-.766,1.227-1.031,1.781.609.895,1.863,1.25,3.875,1.563,2.086.324,4.688.7,4.688,3.406,0,.547,1.992,1.906,5,1.906s5-1.359,5-1.906c0-2.766,2.613-3.152,4.719-3.469,1.984-.3,3.238-.625,3.844-1.5-.27-.551-.637-1.137-1.031-1.781C36.4,23.59,35,21.293,35,18.406a27.747,27.747,0,0,1,1.031-6.687,1.009,1.009,0,0,1,1.938.563A25.849,25.849,0,0,0,37,18.406a11.028,11.028,0,0,0,2.063,5.688A10.841,10.841,0,0,1,40.5,19.219,10.937,10.937,0,0,0,42,13.313C42,5.977,34.375,0,25,0ZM19.813,18C21.711,18,23,19.988,23,21.688A4.084,4.084,0,0,1,18.594,26C17.492,26,16,24.988,16,21.688A3.655,3.655,0,0,1,19.813,18Zm10.375,0A3.655,3.655,0,0,1,34,21.688C34,24.988,32.508,26,31.406,26A4.084,4.084,0,0,1,27,21.688C27,19.988,28.289,18,30.188,18ZM4.563,21.031a2.914,2.914,0,0,0-2.719,1.625,4.086,4.086,0,0,0-.312,3.031A3.419,3.419,0,0,0,0,28.906a3.607,3.607,0,0,0,3.313,3.688,3.8,3.8,0,0,0,1.813-.437.926.926,0,0,1,.406-.125,3.079,3.079,0,0,1,1.094.406l7.281,2.969a31.556,31.556,0,0,0-.5-4.937,5.507,5.507,0,0,1-3.625-2.125,1.948,1.948,0,0,1-.156-1.969c.023-.047.07-.109.094-.156-.328-.125-.652-.262-.969-.375-.637-.23-.723-.469-.844-1.344a3.575,3.575,0,0,0-2.219-3.219A3.1,3.1,0,0,0,4.563,21.031Zm40.875,0a3.277,3.277,0,0,0-1.156.25,3.63,3.63,0,0,0-2.219,3.25c-.121.875-.16,1.1-.844,1.344-.3.121-.605.246-.906.375.016.031.047.063.063.094a2.035,2.035,0,0,1-.156,2.031,5.686,5.686,0,0,1-3.781,2.063,36.6,36.6,0,0,0-.375,4.781l7.375-2.812a2.843,2.843,0,0,1,1.031-.375,1.186,1.186,0,0,1,.406.156,3.873,3.873,0,0,0,1.813.406A3.61,3.61,0,0,0,50,28.906a3.413,3.413,0,0,0-1.531-3.156,4.136,4.136,0,0,0-.312-3.094A2.917,2.917,0,0,0,45.438,21.031ZM24.906,26C26.105,26,28,30.211,28,30.813a1.064,1.064,0,0,1-1.187,1.094c-.8,0-1.707-2.207-1.906-2.906h-.094c-.7,2-1.32,3-1.719,3-.8,0-1.094-.508-1.094-1.406C22,28.992,23.707,26,24.906,26Zm-9.375,4.813a59.9,59.9,0,0,1,.438,6.219c.027.785.059,1.641.094,2C16.848,40,21.578,44,25,44c3.438,0,8.215-4.02,8.938-5.031.027-.293.074-1.234.094-2.062.059-2.32.129-4.512.344-6.094a9.289,9.289,0,0,0-1.469.344c-.129.789-.223,1.5-.312,2.156C31.965,37.941,31.41,40,25,40c-6.5,0-7.055-2.055-7.656-6.719-.082-.637-.164-1.332-.281-2.094A10.3,10.3,0,0,0,15.531,30.813Zm4.031,3.813c.316,1.953.75,2.844,2.438,3.188V35.719A8.058,8.058,0,0,1,19.563,34.625Zm10.813.063A8.182,8.182,0,0,1,28,35.719v2.063C29.609,37.434,30.051,36.586,30.375,34.688ZM24,36.063V38c.313.008.641,0,1,0s.688.008,1,0V36.063c-.328.027-.66.031-1,.031S24.328,36.09,24,36.063Zm12,1.375a9.337,9.337,0,0,1-.156,2.188,9.893,9.893,0,0,1-3.031,3.063,63.421,63.421,0,0,1,5.688,3,4.654,4.654,0,0,1,.375,1.031c.363,1.23.965,3.281,3.313,3.281A3.3,3.3,0,0,0,45,48.75a4.55,4.55,0,0,0,.563-3.469c.059-.023.1-.07.156-.094a3.41,3.41,0,0,0,2.563-2.656,3.176,3.176,0,0,0-.437-2.5,2.955,2.955,0,0,0-2.219-1.219,3.549,3.549,0,0,0-2.812,1.156c-.187.168-.473.465-.687.438C40.93,39.855,36.582,37.723,36,37.438Zm-22.031.094c-1.348.633-5.039,2.363-6.156,2.844-.02.008-.074.055-.094.063A2.666,2.666,0,0,1,7.156,40a3.45,3.45,0,0,0-2.875-1.187,2.9,2.9,0,0,0-2.062,1.063,3.55,3.55,0,0,0-.625,2.563.879.879,0,0,0,.031.094,3.553,3.553,0,0,0,2.531,2.625c.063.027.125.07.188.094a4.665,4.665,0,0,0,.594,3.5A3.207,3.207,0,0,0,7.688,50c2.348,0,2.98-2.051,3.344-3.281a8.166,8.166,0,0,1,.313-1c1.219-.812,4.777-2.551,5.813-3.062a10.915,10.915,0,0,1-3-2.937A11.042,11.042,0,0,1,13.969,37.531Z"/></svg>',
                  warning:
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 37.087 50"><path d="M39.531,7a2.353,2.353,0,0,0-2.5,2.531V23a1,1,0,0,1-1,1A1.026,1.026,0,0,1,35,23V4.48A2.33,2.33,0,0,0,32.52,2a2.417,2.417,0,0,0-2.5,2.508v17.5a1,1,0,0,1-1,1,1.012,1.012,0,0,1-1.016-1V2.508a2.5,2.5,0,1,0-5,0V23a1,1,0,0,1-2,0V6.547a2.5,2.5,0,0,0-5-.113V32.3a.665.665,0,0,1-.133.047l0-.047-5-6a3.384,3.384,0,0,0-4.9,0,3.382,3.382,0,0,0,0,4.9l.109.145a.924.924,0,0,0,.137.27l8.52,10.945,2.73,3.539,0-.035.352.453s0,0,.008,0a8.739,8.739,0,0,0,6.551,3.449c.125.012.262.02.4.023.043,0,.086.008.129.008,0,0,.008,0,.016,0,.027,0,.055,0,.086,0h8a8.912,8.912,0,0,0,9-9V9.582A2.347,2.347,0,0,0,39.531,7Z" transform="translate(-4.913)"/></svg>'
                };
                icon = '<span class="svg">' + icons[icon] + "</span>";
              }

              $(this).html(
                str.replace(/\{(.*?)\}/, '<div class="icon">' + icon + "</div>")
              );
              $(this)
                .parent()
                .addClass("alert is-" + word);
            }
          });

          // Replace markdown checkboxes with html checkboxes
          $(".documentation ul li").each(function() {
              var regex = /\[.+\]/;
              var text  = $(this).text();
              var match = text.match(regex);

              if (match) {
                  $(this).parent().addClass('list-reset pl-0');
                  var checkbox = '<input type="checkbox" disabled=""' + (match[0].includes('x') ? ' checked=""' : '') + '>'
                  var html     = text.replace(regex, checkbox);
                  $(this).html(html);
              }
          });
        },
        setupKeyboardShortcuts() {
          // keyboard magic 🎹
          const Mousetrap = require("./vendor/mousetrap.js");

          // toggle the sidebar
          Mousetrap.bind("/", e => {
            e.preventDefault();
            this.sidebar = !this.sidebar;
          });

          // open the search box
          Mousetrap.bind("s", e => {
            e.preventDefault();
            this.searchBox = true;
          });

          // scroll to the top of the page
          Mousetrap.bind("t", e => {
            e.preventDefault();
            $("html,body").animate(
              {
                scrollTop: 0
              },
              500
            );
          });

          // scroll to the bottom of the page
          Mousetrap.bind("b", e => {
            e.preventDefault();
            $("html,body").animate(
              {
                scrollTop: $(document).height()
              },
              500
            );
          });
        }
      }
    });
  }
}
