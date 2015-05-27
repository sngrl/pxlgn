/* jshint devel:true */
$('.styled-select').selectmenu({
	change: function( event, ui ) {
		var _url = $(ui.item.element).attr('data-url');
		window.location.href=_url;
	}
}).selectmenu( "open" ).selectmenu( "close" );

$(function() {
    $( ".js-selectmenu" ).selectmenu().addClass('ui-hui-pizda-dzigurda');
});

// $('.app-store').mouseover(function(){
// 	var button = $('.app-store');
// 	var x = 25;
//     window.setInterval(function() { 
//         button.css("backgroundPosition", x + 'px');  
// 		x--;   
//     }, 1000);  
// });

$('.app-store').on('mouseover', function(){
	$(this).removeClass('animation-reverse');
	$(this).addClass('animation');
}).on('mouseout', function(){
	$(this).removeClass('animation');
	$(this).addClass('animation-reverse');
});



//Главный слайдер

var mainFotorama = function() {
	var $fotoramaDiv = $('.main-fotorama').fotorama({
		width: 620,
		height: 360,
		loop: true,
		arrows: false
	});
	var fotorama = $fotoramaDiv.data('fotorama');
	$('.js-main-left').on('click', function(){
		fotorama.show('<');
	});
	$('.js-main-right').on('click', function(){
		fotorama.show('>');
	});
}
mainFotorama();


//Слайдер видео

var videoFotorama = function() {
	var $fotoramaDiv = $('.video-fotorama').fotorama({
		width: 246,
		height: 184,
		loop: true,
		arrows: false
	});
	var fotorama = $fotoramaDiv.data('fotorama');
	$('.js-video-left').on('click', function(){
		fotorama.show('<');
	});
	$('.js-video-right').on('click', function(){
		fotorama.show('>');
	});
}
videoFotorama();


//Слайдер скринов

var screenFotorama = function() {
	var $fotoramaDiv = $('.screen-fotorama').fotorama({
		width: 246,
		height: 184,
		loop: true,
		arrows: false,
		click: false,
		swipe: false
	});
	var fotorama = $fotoramaDiv.data('fotorama');
	$('.js-screen-left').on('click', function(){
		fotorama.show('<');
	});
	$('.js-screen-right').on('click', function(){
		fotorama.show('>');
	});
}
screenFotorama();

var ButtonAnimation = function() {
	var anim_time = 150;
	var anim_timeout = [];
	var options = {
		".main-menu .main": {
			steps: 7
		},

		".main-menu .about": {
			steps: 7
		},

		".main-menu .media": {
			steps: 7
		},

		".main-menu .forum": {
			steps: 7
		},

		".main-menu .blog": {
			steps: 7
		},

		// ".main-menu .mobile": {
		// 	steps: 7
		// }

		".header .play-button": {
			steps: 22
		},

		".column-right .button-registration": {
			steps: 5
		},

		".inner-page-menu .li-registration-button": {
			steps: 5
		},

		"li.registration-button": {
			steps: 4
		},

		".social-button-holder .soc-vk": {
			steps: 3
		},

		".social-button-holder .soc-fb": {
			steps: 3
		},

		".social-button-holder .soc-tw": {
			steps: 3
		},

		".store .google-play-holder": {
			steps: 3
		},

		".store .app-store-holder": {
			steps: 3
		}
	};
	var Animation = function(step, elem, direction) {
		var this_steps = options[elem].steps;
		var this_elem = $(elem);
		if(direction == 'hover') {
			step++;
		} else {
			step--;
		}
		var new_pos = step * this_elem.width() * (-1);
		this_elem
			.css('background-position',  new_pos + 'px 0')
			.attr('data-active-step', step);
		anim_timeout[elem] = setTimeout(function(){
			if(step < this_steps - 1 && step > 0) {
				Animation(step, elem, direction);
			}
		}, anim_time/this_steps);
	}
	$.each(options, function(index, value){
		if(!$(index).hasClass('active')) {
			$(index).css('background-size', 100*value.steps + '% 100%');
			$(index).on('mouseenter', function(){
				clearTimeout(anim_timeout[index]);
				var start_step = 0;
				if($(this).attr('data-active-step')) {
					start_step = $(this).attr('data-active-step');
				}
				Animation(start_step, index, 'hover');
			}).on('mouseleave', function(){
				clearTimeout(anim_timeout[index]);
				Animation($(this).attr('data-active-step'), index, 'hoverout');
			});
		}
	});
}
ButtonAnimation();


var mediaMenu = function(){
	var parent = $('.inner-page-menu ul');
	var menu_item = parent.find('li').not('.registration-button');
	var active_menu_item = menu_item.filter('.active');
	var menu_height = 60;
	var setActive = function() {
		parent.css('background-position', '0 ' + '-' + menu_height*active_menu_item.index() + 'px');
	}
	var init = function() {
		setActive();
		menu_item.on('mouseenter', function(){
			var this_index = $(this).index();
			parent.css('background-position', '0 ' + '-' + menu_height*this_index + 'px');
		}).on('mouseleave', function(){
			setActive();
		});
	}
	init();
}
mediaMenu();

//POP-UP окна авторизиции

$(function() {
    $( "#check" ).button();
    $( "#format" ).buttonset();

    $(".button-registration, .registration-button").click(function(e){
    	
        $(".login-form-background").fadeIn(700);
        e.preventDefault();
    });

    $(".close-button").click(function(e){
        $(".login-form-background").fadeOut(700);
        e.preventDefault();
    });

    $(".fancybox").fancybox({})
	
	// ПОКАЗАТЬ-СКРЫТЬ ПАРОЛЬ

	$('#log-in-form button.spice').click(function(e){
		e.preventDefault();

		var $input = $('#log-in-form .pass input');
		var _type = $input.attr('type');

		if (_type == 'password') {
			$input.attr('type', 'text');
		} else if (_type == 'text') {
			$input.attr('type', 'password');
		}
		$('form.page button.spice').toggleClass( "spice-pressed" );
	});

	// РАБОТА ФОРМЫ

	// $('.log-in-button-2 button').click(function(e){
	// 	e.preventDefault();

	// 	$('#log-in-form').toggleClass('log-in-form');

	// });

	// $('.log-in-button-2 button').click(function(e){
	// 	e.preventDefault();

	// 	$('.form-fade').hide();
	// 	$('.success-fade').show();
	// });

	$('.download-button a').click(function(e){
		e.preventDefault();

		$('.form-fade').show();
		$('.success-fade').hide();
	});

	//$('.app-store').

	// ВАЛИДАЦИЯ

  $("#log-in-form").validate({
	rules: {
		email: {
			required: true,
			email: true
		},
		password: {
			required: true,
			minlength: 6,
		},
		agreement: 'required',
		capcha: 'required'

	},
	messages: {
		email: {
			required: 'Обязательное поле!',
			email: 'Неверный формат!'
		},
		password: {
			required: 'Обязательное поле!',
			minlength: 'Слишком простой пароль!',
		},
		agreement: 'Вы забыли принять пользовательское соглашение',
		capcha: 'Неверно введён код'
	},
 	submitHandler: function(form) {
     var _url = $(form).attr('action'),
         _data = $(form).serialize(),
         _method = $(form).attr('method')||'POST';
         $('.js-form-error').hide();
         $('.form-holder [type="submit"]').attr('disabled', 'disabled');
     $.ajax({
       type: _method,
       url: _url,
       data: _data
 	 }).done(function(data){
 	 	
 	 	console.log(data);

 	 	if(data.status == true) {
       		//$('.js-form-success').html(data.responseText);


       		
       		$('.success-fade').slideUp();
       		$('.form-fade').slideDown();
       	}
       	if(!data.status && data.responseText) {
       		$('p.warning').show().html(data.responseText);
       	}
       }).fail(function(data){
       	$('.js-form-error').show().html('Server error');
       }).always(function(){
       	$('.form-holder [type="submit"]').removeAttr('disabled');
       });
 	}
 })

var capchaString = $('.capcha-image img').attr('src');

 $('.capcha-image .refresh').click(function(e) {
 	e.preventDefault();

 	var capchaSrcEdit = function() {
 		function randomShit() {
		  return Math.floor(Math.random() * (9999999+1) );
		}

		$('.capcha-image img').removeAttr('src');
		$('.capcha-image img').attr('src', capchaString + '?' + randomShit());
 	}

 	capchaSrcEdit();
 })

});