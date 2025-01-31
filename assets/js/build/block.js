(()=>{"use strict";var e={20:(e,t,n)=>{var s=n(609),o=Symbol.for("react.element"),r=(Symbol.for("react.fragment"),Object.prototype.hasOwnProperty),a=s.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED.ReactCurrentOwner,i={key:!0,ref:!0,__self:!0,__source:!0};t.jsx=function(e,t,n){var s,c={},d=null,m=null;for(s in void 0!==n&&(d=""+n),void 0!==t.key&&(d=""+t.key),void 0!==t.ref&&(m=t.ref),t)r.call(t,s)&&!i.hasOwnProperty(s)&&(c[s]=t[s]);if(e&&e.defaultProps)for(s in t=e.defaultProps)void 0===c[s]&&(c[s]=t[s]);return{$$typeof:o,type:e,key:d,ref:m,props:c,_owner:a.current}}},848:(e,t,n)=>{e.exports=n(20)},609:e=>{e.exports=window.React}},t={};const n=window.wc.wcBlocksRegistry,s=window.wp.htmlEntities,o=window.wc.wcSettings,r=window.wp.element;class a{constructor(e,t,n,s,o={},r=!1){if(document.getElementById(e)){if(!onvo)throw new Error("Onvo SDK not loaded");if(!t)throw new Error("Public key is required");if(!n)throw new Error("Payment intent id is required");this.publicKey=t,this.paymentIntentId=n,this.customerId=s,this.paymentType="one_time",this.shopper=o,this.debug=r,this.sdk_intance=null,this.containerSelector=`#${e}`}else console.error(e+" not found, cannot render iframe")}renderWidget(e,t,n,s){document.querySelector(this.containerSelector)?(this.sdk_intance=onvo.pay({paymentIntentId:this.paymentIntentId,paymentType:this.paymentType,publicKey:this.publicKey,customerId:this.customerId,debug:this.debug,shopper:this.shopper,onError:e,onSuccess:n,onFormErrors:t,onShopperTrail:s,manualSubmit:!0,branding:!1}),this.sdk_intance.render(this.containerSelector)):console.log(this.containerSelector+" not found, cannot render iframe")}submitPayment(){this.sdk_intance.submitPayment()}}var i=function n(s){var o=t[s];if(void 0!==o)return o.exports;var r=t[s]={exports:{}};return e[s](r,r.exports,n),r.exports}(848);const c=(0,o.getSetting)("wc-onvo-payment-gateway_data"),d=(0,s.decodeEntities)(c.title),m=c.id,p=({activePaymentMethod:e,eventRegistration:t,emitResponse:n})=>{const{onPaymentSetup:s}=t,{responseTypes:o}=n;let d,p={},u={},l={};const y=e=>{var t;u.message=null!==(t=e.message)&&void 0!==t?t:e.lastPaymentError?.message},h=e=>{l.message=e.map((e=>e.message))},_=e=>{p=e},f=e=>{"MOBILE_MODAL_PAYMENT_OPENED"!==e&&"MOBILE_MODAL_PAYMENT_CLOSED"!==e&&"PAYMENT_REQUIRES_ACTION"!==e||document.querySelector(".wc-block-checkout__payment-method").classList.remove("wc-block-components-checkout-step--disabled")},w=async()=>!!(u.message||l.message||p.status&&"succeeded"===p.status)||(await new Promise((e=>setTimeout(e,200))),w());return(0,r.useEffect)((()=>{m===e&&(d||(d=new a(c.id+"-cc-blocks-form",c.publishableKey,c.paymentIntentId||onvo_pay_params.paymentIntentId,c.customerId,c.shopper||{},"1"===c.debug),d.renderWidget(y,h,_,f)))}),[e]),(0,r.useEffect)((()=>{if(m!==e)return;const t=s((async()=>(p={},u={},l={},d.submitPayment(),await w(),l.message?{type:o.FAIL,message:l.message}:u.message?{type:o.ERROR,message:u.message}:p.status&&"succeeded"===p.status?{type:o.SUCCESS,meta:{paymentMethodData:{onvo_intent_id:p.id,onvo_method_id:p.paymentMethodId}}}:void 0)));return()=>{t()}}),[s,e]),(0,i.jsx)("div",{children:(0,i.jsx)("fieldset",{id:`${e}-cc-blocks-form`,className:"wc-credit-card-form wc-payment-form"})})},u=e=>{const{PaymentMethodLabel:t}=e.components;return(0,i.jsx)(t,{text:d})},l={name:c.id,label:(0,i.jsx)(u,{}),content:(0,i.jsx)(p,{}),edit:(0,i.jsx)("div",{children:(0,i.jsx)(u,{})}),canMakePayment:()=>!0,ariaLabel:d,supports:{features:c.supports}};(0,n.registerPaymentMethod)(l)})();