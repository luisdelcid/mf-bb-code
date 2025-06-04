var mf_bb_code = class extends __base {

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return void
	 */
	loader(){
        var $this = this;
        this.editors = [];
        jQuery(function($){
            if(_.isUndefined(ace)){
                return;
            }
            $this.edit($this.slug());
        });
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    /**
     * @return void
     */
    edit(id){
        var $this = this,
            editor = null,
            editor_height = 0,
            editor_rows = 0,
            options = {},
            session = null;
        if(!jQuery('#' + id).length){
            return;
        }
        if(!_.isUndefined($this.editors[id])){
            return;
        }
        editor_height = jQuery('#' + id).height();
        editor_rows = jQuery('#' + id).attr('rows');
        jQuery('#' + id).hide();
        jQuery('<div class="' + $this.slug('editor-container') + '" id="' + id + '-editor-container"><div class="' + $this.slug('editor') + '" id="' + id + '-editor"></div></div>').insertBefore('#' + id);
        editor = ace.edit(id + '-editor');
        options.enableBasicAutocompletion = true;
        options.enableLiveAutocompletion = true;
        options.fontSize = 16;
        options.wrap = true;
        session = editor.getSession();
        if($this.l10n('is_mobile')){
            jQuery('#' + id + '-editor').height(editor_height);
        } else {
            editor.$blockScrolling = Infinity;
            options.maxLines = Infinity;
            options.minLines = editor_rows;
        }
        editor.setOptions(options);
        editor.setTheme('ace/theme/monokai');
        session.on('change', function(){
            jQuery('#' + id).val(session.getValue()).trigger('change');
        });
        session.setMode('ace/mode/' + jQuery('#' + id).data('mode'));
        session.setValue(jQuery('#' + id).val());
        $this.editors[id] = editor;
    }

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

}
