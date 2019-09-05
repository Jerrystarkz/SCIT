//
// window.onscroll = function() {myFunction()};
//
// var header = document.getElementById("myHeader");
// var sticky = header.offsetTop;
//
// function myFunction() {
//   if (window.pageYOffset > sticky) {
//     header.classList.add("sticky");
//   } else {
//     header.classList.remove("sticky");
//   }
// }


// $(window).scroll(function(){
//
// var a = 112;
// var pos = $(window).scrollTop();
// if(pos > a) {
//     $(".header").css({
//                 position: 'fixed'
//             });
// }
// else {
//     $(".header").css({
//                 position: 'absolute',
//                 top:'600px'
//             });
// }
// });



// $(window).scroll(function() {
//     var scroll = $(window).scrollTop();
//
//     if (scroll >= 200) {
//         $(".header").addClass("fixedElement");
//     } else {
//         $(".header").removeClass("fixedElement");
//     }
// });



  $(document).ready(function ()
     { $(".class-span").each(function(i){
          var len=$(this).text().trim().length;
          if(len>200)
          {
              $(this).text($(this).text().substr(0,280)+'...');
          }
      });
   });







$(document).ready(function(){


    //
    // const customSelects = document.querySelectorAll("select");
    // const deleteBtn = document.getElementById('delete')
    // const choices = new Choices('select',
    // {
    //   searchEnabled: false,
    //   removeItemButton: true,
    //   itemSelectText: '',
    // });
    // for (let i = 0; i < customSelects.length; i++)
    // {
    //   customSelects[i].addEventListener('addItem', function(event)
    //   {
    //     if (event.detail.value)
    //     {
    //       let parent = this.parentNode.parentNode
    //       parent.classList.add('valid')
    //       parent.classList.remove('invalid')
    //     }
    //     else
    //     {
    //       let parent = this.parentNode.parentNode
    //       parent.classList.add('invalid')
    //       parent.classList.remove('valid')
    //     }
    //   }, false);
    // }
    // deleteBtn.addEventListener("click", function(e)
    // {
    //   e.preventDefault()
    //   const deleteAll = document.querySelectorAll('.choices__button')
    //   for (let i = 0; i < deleteAll.length; i++)
    //   {
    //     deleteAll[i].click();
    //   }
    // });

});
