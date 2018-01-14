<div class="crm-block crm-form-block crm-googlegroup-setting-form-block">
  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl"}
  </div>
  <table class="form-layout-compressed">
    <tr class="crm-googlegroup-setting-api-key-block">
      <td class="label">{$form.cg_client_key.label}</td>
      <td>{$form.cg_client_key.html}</td>
    </tr>
    <tr class="crm-webinar-setting-api-key-email">
      <td class="label">{$form.cg_client_secret.label}</td>
      <td>{$form.cg_client_secret.html}</td>
    </tr>
    <tr class="crm-webinar-setting-api-key-domain">
      <td class="label">{$form.cg_domain_name.label}</td>
      <td>{$form.cg_domain_name.html}<br/>
        <span class="description">{ts} Enter domain names separated by comma{/ts}</span></td>
    </tr>
  </table>
  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl"}
  </div>
</div>
