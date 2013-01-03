
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
