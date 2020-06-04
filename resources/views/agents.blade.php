{!! Form::open(array('route' => 'route.name', 'method' => 'POST')) !!}
	<ul>
		<li>
			{!! Form::label('nom', 'Nom:') !!}
			{!! Form::text('nom') !!}
		</li>
		<li>
			{!! Form::label('img_cni', 'Img_cni:') !!}
			{!! Form::text('img_cni') !!}
		</li>
		<li>
			{!! Form::label('phone', 'Phone:') !!}
			{!! Form::text('phone') !!}
		</li>
		<li>
			{!! Form::label('reference', 'Reference:') !!}
			{!! Form::text('reference') !!}
		</li>
		<li>
			{!! Form::label('adresse', 'Adresse:') !!}
			{!! Form::text('adresse') !!}
		</li>
		<li>
			{!! Form::label('taux_commission', 'Taux_commission:') !!}
			{!! Form::text('taux_commission') !!}
		</li>
		<li>
			{!! Form::label('email', 'Email:') !!}
			{!! Form::text('email') !!}
		</li>
		<li>
			{!! Form::label('pays', 'Pays:') !!}
			{!! Form::text('pays') !!}
		</li>
		<li>
			{!! Form::submit() !!}
		</li>
	</ul>
{!! Form::close() !!}