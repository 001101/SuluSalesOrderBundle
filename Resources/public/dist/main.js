require.config({paths:{sulusalesorder:"../../sulusalesorder/dist"}}),define({name:"SuluSalesOrderBundle",initialize:function(a){"use strict";var b=a.sandbox;a.components.addSource("sulusalesorder","/bundles/sulusalesorder/dist/components"),b.mvc.routes.push({route:"sales/orders",callback:function(){this.html('<div data-aura-component="orders@sulusalesorder" data-aura-display="list"/>')}}),b.mvc.routes.push({route:"sales/orders/add",callback:function(){this.html('<div data-aura-component="orders/components/content@sulusalesorder" data-aura-display="content" data-aura-content="form" />')}}),b.mvc.routes.push({route:"sales/orders/edit::id/:content",callback:function(a,b){this.html('<div data-aura-component="orders/components/content@sulusalesorder" data-aura-display="content" data-aura-content="'+b+'" data-aura-id="'+a+'"/>')}})}});