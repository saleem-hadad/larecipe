require('./bootsrap');

import Larecipe from './Larecipe';

;(function() {
  this.CreateLarecipe = function(config) {
      return new Larecipe(config)
  }
}.call(window))