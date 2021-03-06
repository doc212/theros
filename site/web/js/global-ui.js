$(function(){
    $(".custom-ui-button").button();
    $(".custom-ui-textfield").button().off("mouseenter").off("focus");
    $(".custom-ui-select").each(function(){
        var $this=$(this);
        $this.selectmenu();
        var menu=$this.selectmenu("menuWidget");
        var button=$this.selectmenu("widget");
        button.click();
        var longest=null;
        menu.children("li").each(function(){
            var $this=$(this);
            if(longest==null || longest.text().length < $this.text().length){
                longest=$this;
            }
        });
        menu.css("width","auto");
        var width=longest.mouseover().width();
        button.click();
        var innerButton = button.find(".ui-selectmenu-text");
        var paddingRight=parseFloat(innerButton.css("padding-right"));
        var paddingLeft=parseFloat(innerButton.css("padding-left"));
        var total=width+paddingLeft+paddingRight + 3;
        $this.selectmenu("option","width", total);

        //forward change event
        $this.on("selectmenuchange", function(){
            $this.trigger("change");
        });
    })
    $(".custom-ui-textarea")
        .addClass("ui-widget ui-corner-all ui-widget-content")
    ;
    $(".custom-ui-buttonset").buttonset();
});

