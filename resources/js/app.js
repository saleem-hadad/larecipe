require('./bootsrap');

import LaRecipe from './LaRecipe';

;(function() {
  this.CreateLarecipe = function(config) {
      return new LaRecipe(config)
  }
}.call(window))