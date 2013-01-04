
function toggleDraftLabel(objElement, strClass, strLabel)
{
	var objParent = objElement.getParent('.tl_content').getElement('.cte_type');
	var objLabel = objParent.getElement('.draft_label.' + strClass );
	
	if(!objLabel)
	{
		var objLabel = new Element('div');
		objLabel.set('class', 'draft_label ' + strClass);
		objLabel.set('text', strLabel);
		objLabel.inject(objParent);
	}
	
	return false;
}

function getUrlVars() {
    var vars = {};
    var parts = window.frames[0].location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m,key,value) {
        vars[key] = value;
    });
    return vars;
}

function addSubmitButton(text)
{
	modal = $('simple-modal');
	
	modal.getElement('.simple-modal-footer .btn').removeClass('primary');
	modal.getElement('.simple-modal-footer .btn').addClass('cancel');
	
	button = new Element('a');
	button.set('text', text);
	button.set('class', 'btn primary');
	
	button.addEvent('click', function() {
		$$('.simple-modal iframe').addEvent('load', function() {
			if(getUrlVars()['reload'] == undefined)
			{
				$$('.simple-modal .cancel').fireEvent('click');			
			}
		});
		
		window.frames[0].document.forms[0].submit();		
	});
	
	button.inject(modal.getElement('.simple-modal-footer'));
	return false;
}

window.addEvent('domready', function() {
	$$('.popup.task .tl_submit_container').destroy();
});
