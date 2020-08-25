/* 初始化所有的命名空间*/
jQuery(function($) {
	$.extend({
		ns: function(namespace, context) {
			var parent = (context == null) ? window : context;
			var arr = namespace.split('.');
			for(var i = 0; i < arr.length; i++) {
				if(!!!parent[arr[i]]) {
					parent[arr[i]] = {};
				}
				parent = parent[arr[i]];
			}

			// 例如: 支持 namespace = $.ns('dao.cube.modules')
			return parent;
		},
		/**
		 * 查看某个Module是否在Namespace中定义， 原理同{$.ns()}
		 * @param {Object} moduleName
		 * @param {Object} context
		 */
		require_module: function(moduleName, context) {
			var parent = (context == null) ? window : context;
			var arr = moduleName.split('.');
			for(var i = 0; i < arr.length; i++) {
				if(!!!parent[arr[i]]) {
					throw new Error("required module not found: " + moduleName);
				}
				parent = parent[arr[i]];
			}
			return parent;
		}
	});
});

jQuery(function($) {

	// 默认配置
	var DefaultConf = {
		'preCount': 3,
		'container': null
	};

	var Slide = function(conf) {
		this.conf = $.extend({}, DefaultConf, conf);
		this.init(conf);
	};

	Slide.prototype = {
		init: function(conf) {
			// 初始状态
			this.preCount = this.conf.preCount;
			this.container = this.conf.container;
			this.items = this.container.find(this.conf.items);
			this.size = this.items.length;
			this.totalPage = Math.ceil(this.size / this.preCount);
			this.itemWidth = this.items.first().outerWidth(true);
			this.pageIndex = 1;

			// 为循环流畅滑动，前后各增加一页
			var aItems = this.items.filter(':lt(' + this.preCount + ')');
			var bItems = this.items.filter(':gt(' + (this.size - this.preCount - 1) + ')');
			aItems.clone().appendTo(this.container);
			bItems.clone().prependTo(this.container);

			// 初始slide的位置
			this.container.css({
				'left': -(this.itemWidth * this.preCount),
				'width': this.itemWidth * (this.size + 2 * this.preCount)
			});
		},
		next: function(callback) {
			this.pageIndex++;
			if(this.pageIndex > this.totalPage) {
				this.pageIndex = 1;
				this.container.css({
					'left': 0
				});
			}
			this.animate(callback);
		},
		prev: function(callback) {
			this.pageIndex--;
			if(this.pageIndex < 1) {
				this.pageIndex = this.totalPage;
				this.container.css({
					'left': -(this.itemWidth * (this.size + this.preCount))
				});
			}
			this.animate(callback);
		},
		jump: function(pIndex, callback) {
			this.pageIndex = (pIndex < 1) ? 1 :
				(pIndex > this.totalPage) ? this.totalPage : pIndex;
			this.animate(callback);
		},
		animate: function(callback) {
			var self = this;
			var distance = -(this.pageIndex * this.preCount * this.itemWidth);
			this.container.animate({
				'left': distance
			}, function() {
				if($.isFunction(callback)) {
					callback.apply(self, [self.pageIndex]);
				}
			});
		}
	};

	var namespaceName = 'dao.search.video.index',
		NameSpace = $.ns(namespaceName);
	NameSpace.Slide = Slide;
});

jQuery(function($) {
	var namespaceName = 'dao.search.video.index',
		NameSpace = $.ns(namespaceName);

	/**
	 * slide模块鼠标hover
	 * 效果：高亮 + 信息框上移 
	 */
	var slideHoverItemSelector = '#slide .bd li';
	NameSpace.slideModsItemMouseHover = function() {
		$(slideHoverItemSelector).live('mouseover', function(evt) {
			var self = $(this);
			self.find('.shadow-vanish').removeClass('inner-shadow');
			var iwrap = self.find('.info-wrap');
			iwrap.stop(true).animate({
				'bottom': 0
			}, 'fast');
		}).live('mouseout', function(evt) {
			var self = $(this);
			self.find('.shadow-vanish').addClass('inner-shadow');
			var iwrap = self.find('.info-wrap');
			iwrap.stop(true).animate({
				'bottom': -24
			}, 'fast');
		});
	};
	var slideHoverItemSelector1 = '#slide1 .bd li';
	NameSpace.slideModsItemMouseHover = function() {
		$(slideHoverItemSelector1).live('mouseover', function(evt) {
			var self = $(this);
			self.find('.shadow-vanish').removeClass('inner-shadow');
			var iwrap = self.find('.info-wrap');
			iwrap.stop(true).animate({
				'bottom': 0
			}, 'fast');
		}).live('mouseout', function(evt) {
			var self = $(this);
			self.find('.shadow-vanish').addClass('inner-shadow');
			var iwrap = self.find('.info-wrap');
			iwrap.stop(true).animate({
				'bottom': -24
			}, 'fast');
		});
	};

});

jQuery(function($) {
	var namespaceName = 'dao.search.video.index',
		NameSpace = $.ns(namespaceName);

	var Slide = $.require_module('dao.search.video.index.Slide');

	NameSpace.initSlide = function() {

		// 创建slide实例
		var $slide = new Slide({
			'preCount': 3,
			'container': $('#slide .cover ul'),
			'items': 'li'
		});
		// 创建slide实例
		var $slide1 = new Slide({
			'preCount': 3,
			'container': $('#slide1 .cover ul'),
			'items': 'li'
		});

		// 当前是否正在动画中
		var inAnimate = false;

		// 左滑动按钮的事件处理
		$('#slide .bd .left-btn').live('click', function(evt) {
			if(inAnimate) {
				return false;
			}
			inAnimate = true;
			$slide.prev(function(pageIndex) {
				inAnimate = false;
				updateTabStatus(pageIndex);
			});
			evt.preventDefault();
		});
		$('#slide1 .bd .left-btn').live('click', function(evt) {
			if(inAnimate) {
				return false;
			}
			inAnimate = true;
			$slide1.prev(function(pageIndex) {
				inAnimate = false;
				updateTabStatus(pageIndex);
			});
			evt.preventDefault();
		});

		// 右滑动按钮的事件处理
		$('#slide .bd .right-btn').live('click', function(evt) {
			if(inAnimate) {
				return false;
			}
			inAnimate = true;
			$slide.next(function(pageIndex) {
				inAnimate = false;
				updateTabStatus(pageIndex);
			});
			evt.preventDefault();
		});
		$('#slide1 .bd .right-btn').live('click', function(evt) {
			if(inAnimate) {
				return false;
			}
			inAnimate = true;
			$slide1.next(function(pageIndex) {
				inAnimate = false;
				updateTabStatus(pageIndex);
			});
			evt.preventDefault();
		});

		// tab 切换事件处理
		$('#slide .tab a').live('click', function(evt) {
			var tab = $(this);
			var tabs = tab.closest('.tab').find('a');
			var pageIndex = tab.data('index');
			if(inAnimate) {
				return false;
			}
			if(!tab.hasClass('cur')) {
				tabs.removeClass('cur');
				tab.addClass('cur');
				inAnimate = true;
				$slide.jump(pageIndex, function() {
					inAnimate = false;
				});
			}
			evt.preventDefault();
		});
		$('#slide1 .tab a').live('click', function(evt) {
			var tab = $(this);
			var tabs = tab.closest('.tab').find('a');
			var pageIndex = tab.data('index');
			if(inAnimate) {
				return false;
			}
			if(!tab.hasClass('cur')) {
				tabs.removeClass('cur');
				tab.addClass('cur');
				inAnimate = true;
				$slide1.jump(pageIndex, function() {
					inAnimate = false;
				});
			}
			evt.preventDefault();
		});
		/**
		 * 更新 tab 状态
		 * page   1  2  3  4  5  6  7  8
		 * idx    1  1  2  2  3  3  4  4
		 */

		// =====================================================
		// = 自动播放
		// =====================================================
		var autoPlay = true;
		$('#slide, #slide .bd li').live('mouseover', function(evt) {
			autoPlay = false;
		}).live('mouseout', function(evt) {
			autoPlay = true;
		});
		$('#slide1, #slide1 .bd li').live('mouseover', function(evt) {
			autoPlay = false;
		}).live('mouseout', function(evt) {
			autoPlay = true;
		});
		window.setInterval(function() {
			if(!autoPlay) return;
			inAnimate = true;
			$slide.next(function(pageIndex) {
				inAnimate = false;
				updateTabStatus(pageIndex);
			});
		}, 4000);
		window.setInterval(function() {
			if(!autoPlay) return;
			inAnimate = true;
			$slide1.next(function(pageIndex) {
				inAnimate = false;
				updateTabStatus(pageIndex);
			});
		}, 4000);
	};
});

jQuery(function($) {
	var namespaceName = 'dao.search.video.index',
		NameSpace = $.ns(namespaceName);
	// 初始化首页一些模块的鼠标hover效果
	NameSpace.slideModsItemMouseHover();
	// 初始化slide模块
	NameSpace.initSlide();

});

