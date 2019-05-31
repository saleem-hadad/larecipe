require('./bootsrap');

import LaRecipe from './Larecipe';

;(function() {
  this.CreateLarecipe = function(config) {
      return new LaRecipe(config)
  }
}.call(window));
