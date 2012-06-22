#!/usr/bin/env ruby
#encoding: utf-8

require 'nokogiri'
require 'open-uri'
require 'json'
require 'mongo'


puts "#{Time.new().to_s} - Empezando el crawler"

begin
  xml = Nokogiri::XML(open('http://p12.ife.mx/documentos/PREP/2012/MOVILXML.XML'))
rescue Exception => e
  puts 'ERROR!'
  puts 'El IFE no quiso responder :('
  exit 1
end
fecha = xml.at_xpath('//fechaActualizacion').text+' '+xml.at_xpath('//horaActualizacion')

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
         resultados[nombre] = partido.at_xpath("entidades").text.to_i
       elsif partido.at_xpath('distritos')
         resultados[nombre] = partido.at_xpath("distritos").text.to_i
      else
         resultados[nombre] = {votos: partido.at_xpath("votos#{extra}").text.to_i, porcentaje: partido.at_xpath("porcentaje#{extra}").text.to_i}
       end
     
    rescue Exception => e
      puts '--------'
      puts "No pude ingestar #{nombre} de #{key}!"
      puts partido.inspect
      puts '--------'
    end
  end
  
  rs[key.to_sym] = {
    'avance' => if node.actasProcesadas.text.to_i>0 then  node.actasTotales/node.actasProcesadas  else 0; end,
    'resultados' => resultados
  }
end

$mongo = Mongo::Connection.new.db('prep2012')
$mongo['resultados'].insert({
  fecha: fecha,
  run_at: Time.new()
}.merge(rs))


puts "#{Time.new().to_s} - Crawler terminó"