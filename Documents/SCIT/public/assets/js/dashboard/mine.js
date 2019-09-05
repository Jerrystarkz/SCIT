let $$;
(function(){
    let i = Object.prototype.toString;
	class utils{constructor(){};static objExtend(mainData,newData){
        if($$.isObject(newData) && $$.isObject(mainData)){
            $.each(newData,(key,value) => {
                if($$.isArray(value)){
                    if(!$$.isArray(mainData[key])){
                        mainData[key] = value;
                    }else{
                        mainData[key] = mainData[key].concat(value);
                    }
                }else if($$.isObject(value)){
                    if(!$$.isObject(mainData[key])){
                        mainData[key] = value;
                    }else{
                        utils.objExtend(mainData[key],value);
                    }
                }else{
                    mainData[key] = value;
                }
            });
        }};static formDataValuesFromObj(haystack,formData,prepend = false){if(utils.isObject(haystack)){$.each(haystack,(key,value)=>{if(!(utils.isObject(value) || utils.isArray(value))){if(!prepend){formData.append(`${key}`,value);}else{formData.append(`${prepend}[${key}]`,value);}}else{let nPrepend = '';if(!prepend){nPrepend = key;}else{nPrepend = `${prepend}[${key}]`}utils.formDataValuesFromObj(value,formData,nPrepend);}})}else if(utils.isArray(haystack) && prepend){$.each(haystack,(i,value) => {if(!(utils.isObject(value) || utils.isArray(value))){formData.append(`${prepend}[]`,value);}else{utils.formDataValuesFromObj(value,formData,`${prepend}[]`);}});}};static isArray(a){return i.call(a) == '[object Array]';};static isObject(a){return i.call(a) == '[object Object]';};static isString(a){return i.call(a) == '[object String]';};static isDate(a){return i.call(a) == '[object Date]';};static isNull(a){return i.call(a) == '[object Null]';};static isNumber(a){return i.call(a) == '[object Number]';};static mk(a){return Symbol(a);};	static extend(...obj){let copy =(from,to)=>{Object.defineProperties(to,Object.getOwnPropertyDescriptors(from));},obj_copy=(from,to)=>{let a=Object.getOwnPropertyNames(from).concat(Object.getOwnPropertySymbols(from)),b=['name','prototype','length'];for(const x of a){if(b.indexOf(x)===-1){to[x]=from[x];}}},wrap=class {constructor(...args){let count=0;for(const c of obj){let t = new c(...args[count]);copy(t,this);copy(c.prototype,wrap.prototype);obj_copy(c,wrap);count++;}};};return wrap;};static dom(a){function item(a){if(typeof a == 'symbol'){return a.toString().match(/^Symbol\(([\s\S]*)\)$/)[1];}else{return a;}}if(typeof a === 'object'){var node_closed = ['a','address','article','aside','b','blockquote','div','dl','dt','dd','figure','figcaption','footer','h1','h2','h3','h4','h5','h6','header','ol','ul','li','ins','main','nav','noscript','pre','section','script','abbr','dfn','em','strong','code','keyboard','samp','var','u','s','audio','time','span','font','button','select','option','form','bdo','bdi','cite','del','mark','sub','sup','template','canvas','embed','map','object','param','source','track','video','datalist','fieldset','p','label','i','table','tr','th','td','tbody','thead','textarea'],node_opened = ['input','wbr','br','hr','link','img'],main = false;function loop_through(a){var data,aa = Object.getOwnPropertySymbols(a).concat(Object.getOwnPropertyNames(a));for(var i = 0;i < aa.length;i++){var key = aa[i],value = a[key];if(node_closed.indexOf(item(key))!==-1 || node_opened.indexOf(item(key))!==-1){data = document.createElement(item(key));if(typeof value === 'object'){var bb = Object.getOwnPropertySymbols(value).concat(Object.getOwnPropertyNames(value));for(var j = 0; j < bb.length; j++){var key1 = bb[j],value1 = value[key1];switch(true){case (node_closed.indexOf(item(key1))!==-1 || node_opened.indexOf(item(key1))!==-1):data.appendChild(loop_through({[item(key1)]:value1}));break;case (item(key1) === 'html'):$(data).html(value1);break;case (item(key1) === 'text'):$(data).text(value1);break;case (item(key1) === 'css'):$(data).css((typeof value1 === 'object')?value1:{});break;case (item(key1) === 'data'):$(data).data(value1);break;default:$(data).attr(item(key1),value1);}}}return data;}}}return loop_through(a);}else{alert('Fatal variable error');}};static binaryStringToBlob(binaryString){var binaryStringLength = binaryString.length,arr = new Uint8Array(binaryStringLength);for(let i = 0; i < binaryStringLength; i++){arr[i] = binaryString.charCodeAt(i);}return new Blob([arr],{'type':'application/octet-stream'});};}
$$=utils;
}());

let InlineLoader = (function(){
    let classValue = '--__InlineLoader__--';

    class InlineLoader{

        constructor(parentElem, coverElem,customCss = {}) {
            if(typeof parentElem == 'string'){
                this.pe = $(parentElem);
            }else if(typeof parentElem == 'object'){
                this.pe = parentElem;
                if (typeof this.pe.css != 'function') {
                    this.pe = $(this.pe);
                }
            }

            this.classValue = classValue;
            this.ce = coverElem || false;

            if (this.ce) {
                if (typeof this.ce == 'string') {
                    this.ce = $(this.ce);
                } else if (typeof this.ce == 'object') {
                    if (typeof this.ce.css != 'function') {
                        this.ce = $(this.ce);
                    }
                }
            }

            this.position;
            this.zIndex;
            this.customCss = ($.isPlainObject(customCss) ? customCss : {});
            this.timerHolder;
        };

        remove(callback = false){
            let __self = this;

            __self.timerHolder = setTimeout(() => {
                if (typeof __self.pe == 'object') {
                    let loader = __self.pe.find(`.${__self.classValue}`);
                    if (loader.length) {
                        loader.css({ display: 'none' }).remove();
                        __self.pe.css({ 'z-index': __self.zIndex });
                        __self.pe.css({ 'position': __self.position });
                        __self.pe[0].removeAttribute('disabled');
                        if (typeof callback == 'function') {
                            callback();
                        }
                    } else if (typeof callback == 'function') {
                        callback();
                    }
                }
            }, 1000);
        };

        add(){
            let __self = this;

            if (__self.timerHolder) {
                clearTimeout(__self.timerHolder);
            }

            if (typeof __self.pe == 'object') {
                if (!__self.pe.find(`div.${__self.classValue}`).length) {
                    __self.zIndex = __self.pe.css('z-index');

                    let peZIndex = (parseInt(__self.zIndex) == __self.zIndex) ? parseInt(__self.zIndex) : 0,
                        ceZIndex = 999999999999,
                        position = __self.pe.css('position').toLowerCase(),
                        css = $.extend({},{
                            position: 'absolute',
                            width: '100%',
                            height: '100%',
                            display: 'flex',
                            'flex-direction': 'column',
                            'justify-content': 'center',
                            'align-items': 'center',
                            background: 'rgba(255,255,255,0.6)',
                            'z-index': `${ceZIndex}`,
                            top: '0',
                            left: '0',
                            'animation': 'fadeIn 0.2s',
                            fontSize: '200%',
                            color: 'rgb(40,40,40)'
                        },this.customCss);


                    __self.position = position;
                    __self.pe[0].setAttribute('disabled', true);
                    __self.pe.css({ position: (position == 'absolute' ? 'absolute' : 'relative'), 'z-index': `${peZIndex}` });
                    if (typeof __self.ce == 'object') {
                        __self.pe.append($$.dom({
                            [$$.mk('div')]: {
                                class: __self.classValue,
                                css: css,
                                html: __self.ce.html
                            }
                        }));
                    } else {
                        __self.pe.append($$.dom({
                            [$$.mk('div')]: {
                                class: __self.classValue,
                                css: css,
                                i: {
                                    class: 'fa fa-cogs fa-spin'
                                }
                            }
                        }));
                    }
                }
            }
        };
    }
    return InlineLoader;
})();

(function ($) {
    $.QueryString = (function (paramsArray) {
        let params = {};

        for (let i = 0; i < paramsArray.length; ++i) {
            let param = paramsArray[i]
                .split('=', 2);

            if (param.length !== 2)
                continue;

            params[param[0]] = decodeURIComponent(param[1].replace(/\+/g, " "));
        }

        return params;
    })(window.location.search.substr(1).split('&'))
})(jQuery);

const globalLoader = new InlineLoader($(document.body), null, {
    'position': 'fixed',
    'fontSize': '400%',
});
window.isMoxieLoaded = false;

class Processor{
    addLoader(){
        globalLoader.add();
    }

    removeLoader(){
        globalLoader.remove();
    }

    loadMoxie(){
        if(typeof moxie !== 'object'){
            let script = document.createElement('script');
            script.addEventListener('load',function(){
                var timeInterval = setInterval(() => {
                    if(typeof moxie == 'object'){
                        clearInterval(timeInterval);
                        moxie.core.utils.Env.swf_url = '/assets/js/dashboard/Moxie.swf';
                        moxie.core.utils.Env.xap_url = '/assets/js/dashboard/Moxie.xap';
                        window.isMoxieLoaded = true;
                    }
                },300);
            });
            script.src = '/assets/js/dashboard/plupload.full.min.js';
            document.body.appendChild(script);
        }
    }
}
