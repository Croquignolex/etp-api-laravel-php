{!! Form::open(array('route' => 'route.name', 'method' => 'POST')) !!}
	<ul>
		<li>
			{!! Form::label('id_caisse', 'Id_caisse:') !!}
			{!! Form::text('id_caisse') !!}
		</li>
		<li>
			{!! Form::label('id_agent', 'Id_agent:') !!}
			{!! Form::text('id_agent') !!}
		</li>
		<li>
			{!! Form::label('id_flote', 'Id_flote:') !!}
			{!! Form::text('id_flote') !!}
		</li>
		<li>
			{!! Form::label('montant', 'Montant:') !!}
			{!! Form::text('montant') !!}
		</li>
		<li>
			{!! Form::label('note', 'Note:') !!}
			{!! Form::textarea('note') !!}
		</li>
		<li>
			{!! Form::label('reste_sur_versement', 'Reste_sur_versement:') !!}
			{!! Form::text('reste_sur_versement') !!}
		</li>
		<li>
			{!! Form::submit() !!}
		</li>
	</ul>
{!! Form::close() !!}