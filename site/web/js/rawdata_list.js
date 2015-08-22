$(function(){
    $("div#raw_data tr").click(function(){
        location.href=$(this).find("input").val();
    });

    var cssTreated=$("#cssTreated");
    var checkbox=$("#cbTreatedToo").change(function(){
        var checked=$(this).is(":checked");
        Cookies.set("showTreated", checked, {path:"", expires:365});
        if(checked){
            cssTreated.detach();
        }else{
            cssTreated.appendTo("head");
        }
    });
    checkbox[0].checked = Cookies.get("showTreated");
    checkbox.change();

});
