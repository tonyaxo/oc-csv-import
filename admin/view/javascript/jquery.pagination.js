(function( $ ){

  $.fn.pagination = function( options ) {  

    // Создаём настройки по-умолчанию, расширяя их с помощью параметров, которые были переданы
    var settings = $.extend( {
      'count': 10,
	  'container': null,
    }, options);

    return this.each(function() {        
		
		var elements = $(options.container).children();
		
		$(this).text(elements.length);
    });

  };
})( jQuery );