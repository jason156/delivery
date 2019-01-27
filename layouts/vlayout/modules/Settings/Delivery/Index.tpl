{literal}
<style>
    .sys table{border-collapse: collapse}
    .sys td:nth-child(odd){font-weight:bold; text-align:right; font-family: Monospace}
    .sys td {border: 1px solid #aaa; padding: 10px 20px}
    .sys tr:nth-child(even){background: #eee}
</style>
{/literal}
<h2>Delivery prefs</h2>
<table class="sys">
<caption>System Info</caption>
{foreach key=K item=V from=$STATS}
<tr><td>{$K}</td><td>{$V}</td></tr>
{/foreach}
</table>
