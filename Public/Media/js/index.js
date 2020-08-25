$(function() {
    function t(t) {
        var n, a = ["webkit", "Moz", "ms", "o"], e = [], o = document.documentElement.style, s = function(t) {
            return t.replace(/-(\w)/g, function(t, n) {
                return n.toUpperCase()
            })
        }
        ;
        for (n in a)
            e.push(s(a[n] + "-" + t));
        e.push(s(t));
        for (n in e)
            if (e[n] in o)
                return !0;
        return !1
    }
    $("body").css("transform", "scan(0.75)");
    var n = $(window)
      , a = n.height()
      , e = n.width()
      , o = $(document).height()
      , s = !1
      , i = sessionStorage.getItem("section") || 0
      , c = function(t) {
        return 0 > t ? i++ : i--,
        0 > i && (i = 0),
        i > 6 && (i = 6),
        i
    }
    ;
    $("html,body").on("mousewheel", function(t, n) {
        if (s) {
            var a = c(n);
            $(".right_nav a[data-idx='" + (a > 6 ? 6 : a) + "']").trigger("click")
        }
        return !1
    }),
    $(".tips").on("mousewheel", function() {
        return !1
    }),
    $(".right_nav").on("click", "a:not(.active)", function() {
        s && (i = $(this).data("idx"),
        $(this).addClass("active").siblings(".active").removeClass("active"),
        sessionStorage.setItem("section", i > 6 ? 6 : i),
        s = !1,
        $("html,body").stop().animate({
            scrollTop: i * a
        }, 1200, function() {
            s = !0,
            $(".section:eq(" + i + ")").addClass("active").siblings(".active").removeClass("active")
        }))
    }),
    n.on("beforeunload", function() {
        $("html,body").stop(!0, !0).scrollTop(a * parseInt(i))
    }),
    $(".section:eq(" + (i > 5 ? 5 : i) + ")").addClass("active"),
    $(".right_nav a[data-idx='" + i + "']").addClass("active"),
    setTimeout(function() {
        s = !0
    }, 800);
    /*var r = $(".section:(.section-b) .content")*/
    var r = $(".section-b .content")
      , z = $(".section>.title")
      , d = $(".section-a")
      , l = d.find(".content")
      , u = scale2 = e / 1920;
    u > .8 && (u = .8),
    a > e && d.addClass("sp"),
    r.css("transform", "scale(" + u + ")"),
    /* z.css("transform", "scale(" + u + ")"), */
    t("zoom") ? l.css("zoom", scale2) : l.css("transform", "scale(" + scale2 + ")"),
    n.on("resize", function() {
        a = n.height(),
        e = n.width(),
        o = $(document).height(),
        u = scale2 = e / 1920,
        u > .8 && (u = .8),
        a > e ? d.addClass("sp") : d.removeClass("sp"),
        r.css("transform", "scale(" + u + ")"),
        /* z.css("transform", "scale(" + u + ")"), */
        t("zoom") ? l.css("zoom", scale2) : l.css("transform", "scale(" + scale2 + ")")
    });
    
    var m = $(".section-a .wave-1")
      , v = $(".section-a .wave-2")
      , w = 10
      , b = 10
      , k = 108
      , C = 0;
    setInterval(function() {
        k -= 1191,
        m.animate({
            backgroundPositionX: k
        }, 45999, "linear"),
        w = 46e3
    }, w),
    setInterval(function() {
        C -= 1206,
        v.animate({
            backgroundPositionX: C
        }, 23999, "linear"),
        b = 24e3
    }, b),
    /*$(".download_ios").on("click", function() {
        $(this).parents(".section-a").addClass("blur"),
        $(".tips").stop().fadeIn(300)
    }),*/
    $(".tips .bg").on("click", function() {
        $(".section-a").removeClass("blur"),
        $(this).parent(".tips").stop().fadeOut(300)
    }),
    $("[data-ek]").on("click", function() {
        var t = $(this);
        $.get("//dr.gpweb.guopan.cn/images/pc.gif?productid=1002&eventkey=" + t.data("ek")).always(function() {
            t.data("href") && (location.href = t.data("href"))
        })
    }),
    $("[data-ga]").on("click", function() {
        if ("" != $(this).data("ga")) {
            var t = $(this).data("ga").split(",");
            ga("send", "event", t[0], t[1], t[2])
        }
    })
});
