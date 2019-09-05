$(document).ready(function()
{    $("#results").click(function() {

if (!$("input[@name=q1]:checked").val() ||
!$("input[@name=q2]:checked").val() ||
!$("input[@name=q3]:checked").val() ||
!$("input[@name=q4]:checked").val() ||
!$("input[@name=q5]:checked").val()

) {
swal("SORRY!", "You are not done with the questions!", "error");     
}

else {
var cat1name = "1";
var cat2name = "2";
var cat3name = "3";
var cat4name = "4";
var cat5name = "5";
var cat6name = "None";


var cat1 = ($("input[@name=q1]:checked").val() != "b");

var cat2 = ($("input[@name=q2]:checked").val() != "c");

var cat3 = ($("input[@name=q3]:checked").val() != "a");

var cat4 = ($("input[@name=q4]:checked").val() != "d");

var cat5 = ($("input[@name=q5]:checked").val() != "c");

var cat6 = (!cat1 && !cat2 && !cat3 && !cat4 && !cat5); var categories = [];

if (cat1) { categories.push(cat1name) };
if (cat2) { categories.push(cat2name) };
if (cat3) { categories.push(cat3name) };
if (cat4) { categories.push(cat4name) };
if (cat5) { categories.push(cat5name) };
if (cat6) { categories.push(cat6name) };

var catStr = 'You answered the following questions incorrectly: ' + categories.join(', ') + '';
$("#categorylist").text(catStr);
$("#categorylist").show("slow");

if (cat1) { $("#category1").show("slow"); };
if (cat2) { $("#category2").show("slow"); };
if (cat3) { $("#category3").show("slow"); };
if (cat4) { $("#category4").show("slow"); };
if (cat5) { $("#category5").show("slow"); };
if (cat6) { $("#category6").show("slow"); };

}
    });});
