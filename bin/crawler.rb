#!/usr/bin/env ruby
#encoding: utf-8

require 'nokogiri'
require 'open-uri'
require 'httparty'
require 'json'
require 'mongo'
require 'date'

puts "#{Time.new().to_s} - Empezando el crawler"


$endpoints = [
	'http://207.249.77.11/prep/MOVILXML.xml',
	'http://74.200.195.178/prep/MOVILXML.xml',
	'http://elecciones2012.gruporeforma.com/prep/MOVILXML.xml',
	'http://ife.canal22.org.mx/prep/MOVILXML.xml',
	'http://prep.elecciones.terra.com.mx/prep/MOVILXML.xml',
	'http://prep.eluniversal.com.mx/prep/MOVILXML.xml',
	'http://prep.milenio.com/prep/MOVILXML.xml',
	'http://prep.unotv.com/prep/MOVILXML.xml',
	'http://prep2012.aztecanoticias.com.mx/prep/MOVILXML.xml',
	'http://prep2012.elimparcial.com/prep/MOVILXML.xml',
	'http://prep2012.noticierostelevisa.esmas.com/prep/MOVILXML.xml',
	'http://prep2012.notimex.com.mx/prep/MOVILXML.xml',
	'http://prep2012.radioformula.com.mx/prep/MOVILXML.xml',
	'http://r12.eleccionesenmexico.mx/prep/MOVILXML.xml',
	'http://www.difusorife.ipn.mx/prep/MOVILXML.xml',
  'http://www.difusorPREP-elecciones2012.unam.mx/prep/MOVILXML.xml'
]

def getServer
  if ($endpoints.count == 0)
    puts "Algo se cagó, no pude encontrar el XML en ningún endpoint!";
    exit
  end
  server = $endpoints.sample
  puts "intentando #{server}"
  begin
    xml = HTTParty.get(server, timeout:5).body;
    return Nokogiri::XML(xml.to_s)
  rescue Exception => e
    puts "#{server} no jaló, va el que sigue"
    $endpoints.delete(server)
    puts $endpoints[0]
    getServer
  end
end

$mongo = Mongo::Connection.new.db('prep2012')

xml = getServer
host = 'localhost'
  
$mongo = Mongo::Connection.new(host).db('prep2012')

dia = '2012-07-02'#xml.at_xpath('//fechaActualizacion').text.strip

hora = xml.at_xpath('//horaActualizacion').text.strip.gsub(/ hrs. \(UTC-5\)/, '')+':00 -0500';
#00:00 hrs. (UTC-5)
fecha = dia+' '+hora
fecha = Time.parse(fecha)

#puts fecha
#exit;

puts "Resultados actualizados en #{fecha}"

last = $mongo['prep'].find_one({}, {sort:['fecha', -1]});
if last && last['fecha'] >= fecha
  puts "No actualizo, ya tengo estos datos"
 exit;
end

puts last
puts fecha

candidaturas = xml.xpath('//candidatura')

rs = {
  presidente: {},
  diputados: {},
  senadores: {}
}

class Candidatura
  
  def initialize(node)
    @node = node;
  end
  
  def method_missing(method)
    return @node.at_xpath(method.to_s)
  end
  
end


candidaturas.each do |c|
  node = Candidatura.new(c);
  key = node.nombreCandidatura.text
  resultados = {}
  #pp c
  c.xpath('partido|extra').each do |partido|
    extra = if partido.name!='extra' then '' else '_extra' end;
    nombre = partido.at_xpath("siglas#{extra}").text
    begin
      
      if partido.at_xpath('entidades')
         resultados[nombre] = (partido.at_xpath("entidades").text.to_i*3.125).floor
       elsif partido.at_xpath('distritos')
         resultados[nombre] = partido.at_xpath("distritos").text.to_i/3
      else
         resultados[nombre] = {votos: partido.at_xpath("votos#{extra}").text.gsub(',', '').to_i, porcentaje: partido.at_xpath("porcentaje#{extra}").text.gsub(',', '').to_i}
       end
     
    rescue Exception => e
      puts '--------'
      puts "No pude ingestar #{nombre} de #{key}!"
      puts partido.inspect
      puts '--------'
    end
  end
  
  caca = if node.actasProcesadas.text.gsub(',','').to_i>0 then ((node.actasProcesadas.text.gsub(',','').to_f/node.actasTotales.text.gsub(',','').to_f)*100).floor  else 0; end;
  
  rs[key.to_sym] = {
    'avance' => if node.actasProcesadas.text.gsub(',','').to_i>0 then ((node.actasProcesadas.text.gsub(',','').to_f/node.actasTotales.text.gsub(',','').to_f)*100).floor  else 0; end,
    'resultados' => resultados
  }
end

$mongo['resultados'].insert({
  fecha: fecha,
  run_at: Time.new()
}.merge(rs))


puts "#{Time.new().to_s} - Crawler terminó"