$(document).ready(function(){
    $("a#sms-record").parent().hide();
    $(".nav-pills").append(
        $("<li></li>").append(
            $("<a></a>").attr("href", "http://google.com")
            .append($("<i></i>").attr("class", "fa fa-infolis"))
            .append("Forschungsdaten suchen!")
        )
    );
});
