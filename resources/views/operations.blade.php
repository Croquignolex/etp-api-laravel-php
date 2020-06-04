{!! Form::open(array('route' => 'route.name', 'method' => 'POST')) !!}
	<ul>
		<li>
			{!! Form::label('id_versement', 'Id_versement:') !!}
			{!! Form::text('id_versement') !!}
		</li>
		<li>
			{!! Form::label('id_motif', 'Id_motif:') !!}
			{!! Form::text('id_motif') !!}
		</li>
		<li>
			{!! Form::label('id_user', 'Id_user:') !!}
			{!! Form::text('id_user') !!}
		</li>
		<li>
			{!! Form::label('description', 'Description:') !!}
			{!! Form::textarea('description') !!}
		</li>
		<li>
			{!! Form::label('flux', 'Flux:') !!}
			{!! Form::text('flux') !!}
		</li>
		<li>
			{!! Form::submit() !!}
		</li>
	</ul>
{!! Form::close() !!}