# coding=utf-8
from __future__ import print_function
import os
import sys
import sqlite3
import json
import re
from indic_transliteration import sanscript
import xml.etree.ElementTree as ET
from flask import Flask, jsonify
from flask_restplus import Api, Resource, reqparse
from flask_cors import CORS
try:
	from HTMLParser import HTMLParser
except ImportError:
	from html.parser import HTMLParser
app = Flask(__name__)
app.config['JSON_AS_ASCII'] = False
app.config.SWAGGER_UI_REQUEST_DURATION = True
CORS(app)
apiversion = 'v0.0.1'
api = Api(app, version=apiversion, title=u'Cologne Sanskrit-lexicon API', description='Provides APIs to Cologne Sanskrit lexica.')


cologne_dicts = ['acc', 'ae', 'ap90', 'ben', 'bhs', 'bop', 'bor', 'bur', 'cae', 'ccs', 'gra', 'gst', 'ieg', 'inm', 'krm', 'mci', 'md', 'mw', 'mw72', 'mwe', 'pe', 'pgn', 'pui', 'pw', 'pwg', 'sch', 'shs', 'skd', 'snp', 'stc', 'vcp', 'vei', 'wil', 'yat']

def find_sqlite(dict):
	path = os.path.abspath(__file__)
	if path.startswith('/nfs/'):
		intermediate = os.path.join(dict.upper() + 'Scan', '2020', 'web', 'sqlite', dict + '.sqlite')
	else:
		intermediate = dict
	sqlitepath = os.path.join('..', intermediate, 'web', 'sqlite', dict + '.sqlite')
	return sqlitepath

def convert_sanskrit(text, inTran, outTran):
	text1 = ''
	counter = 0
	# Remove '<srs/>'
	text = text.replace('<srs/>', '')
	for i in re.split('<s>([^<]*)</s>', text):
		if counter % 2 == 0:
			text1 += i
		else:
			text1 += '<span class="s">' + sanscript.transliterate(i, 'slp1', outTran) + '</span>'
		counter += 1
	# PE nesting of LB tag
	text1 = text1.replace('<div n="1"/>', 'emsp;<div n="1"></div>')
	text1 = text1.replace('<div n="2"/>', 'emsp;emsp;<div n="2"></div>')
	text1 = text1.replace('<div n="3"/>', 'emsp;emsp;emsp;<div n="3"></div>')
	text1 = text1.replace('<div n="4"/>', 'emsp;emsp;emsp;emsp;<div n="4"></div>')
	text1 = text1.replace('<div n="5"/>', 'emsp;emsp;emsp;emsp;emsp;<div n="5"></div>')
	#text1 = re.sub('<div n="([^"]*)"/>', '<div n="\g<1>"></div>', text1)
	text1 = text1.replace('<lb/>', '<br />')
	# AP90 compounds and meanings break
	text1 = text1.replace('<b>--', '<br /><b>--')
	text1 = text1.replace('<span class="s">--', '<br /><span class="s">--')
	# — breaks
	text1 = text1.replace('— ', '<br />— ')
	return text1


def block1(data, inTran='slp1', outTran='slp1'):
	root = ET.fromstring(data)
	key1 = root.findall("./h/key1")[0].text
	key2 = root.findall("./h/key2")[0].text
	pc = root.findall("./tail/pc")[0].text
	lnum = root.findall("./tail/L")[0].text
	m = re.split('<body>(.*)</body>', data)
	text = m[1]
	text1 = convert_sanskrit(text, inTran, outTran)
	return {'key1': key1, 'key2': key2, 'pc': pc, 'text': text, 'modifiedtext': text1, 'lnum': lnum}


@api.route('/' + apiversion + '/dicts/<string:dict>/lnum/<string:lnum>')
@api.doc(params={'dict': 'Dictionary code.', 'lnum': 'L number.'})
class LnumToData(Resource):
	"""Return the JSON data regarding the given Lnum."""

	get_parser = reqparse.RequestParser()

	@api.expect(get_parser, validate=True)
	def get(self, dict, lnum):
		sqlitepath = find_sqlite(dict)
		con = sqlite3.connect(sqlitepath)
		ans = con.execute('SELECT * FROM ' + dict + ' WHERE lnum = ' + str(lnum))
		result = []
		for [headword, lnum, data] in ans.fetchall():
			result.append(block1(data))
		final = {dict: result}
		return jsonify(final)
 

@api.route('/' + apiversion + '/dicts/<string:dict>/hw/<string:hw>')
@api.doc(params={'dict': 'Dictionary code.', 'hw': 'Headword to search.'})
class hwToData(Resource):
	"""Return the JSON data regarding the given headword."""

	get_parser = reqparse.RequestParser()

	@api.expect(get_parser, validate=True)
	def get(self, dict, hw):
		sqlitepath = find_sqlite(dict)
		con = sqlite3.connect(sqlitepath)
		ans = con.execute("SELECT * FROM " + dict + " WHERE key = " + "'" + hw + "'")
		result = []
		for [headword, lnum, data] in ans.fetchall():
			result.append(block1(data))
		final = {dict: result}
		return jsonify(final)


@api.route('/' + apiversion + '/dicts/<string:dict>/reg/<string:reg>')
@api.doc(params={'dict': 'Dictionary code.', 'reg': 'Find the headwords matching the given regex.'})
class regexToHw(Resource):
	"""Return the headwords matching the given regex."""

	get_parser = reqparse.RequestParser()

	@api.expect(get_parser, validate=True)
	def get(self, dict, reg):
		sqlitepath = find_sqlite(dict)
		con = sqlite3.connect(sqlitepath)
		ans = con.execute("SELECT * FROM " + dict )
		result = []
		for [headword, lnum, data] in ans.fetchall():
			if re.search(reg, headword):
				result.append(block1(data))
		final = {dict: result}
		return jsonify(final)


@api.route('/' + apiversion + '/hw/<string:hw>')
@api.doc(params={'hw': 'Headword to search in all dictionaries.'})
class hwToAll(Resource):
	"""Return the entries of this headword from all dictionaries."""

	get_parser = reqparse.RequestParser()

	@api.expect(get_parser, validate=True)
	def get(self, hw):
		final = {}
		for dict in cologne_dicts:
			sqlitepath = find_sqlite(dict)
			con = sqlite3.connect(sqlitepath)
			ans = con.execute("SELECT * FROM " + dict + " WHERE key = " + "'" + hw + "'")
			result = []
			for [headword, lnum, data] in ans.fetchall():
				result.append(block1(data))
			final[dict]  = result
		return jsonify(final)


@api.route('/' + apiversion + '/hw/<string:hw>/<string:inTran>/<string:outTran>')
@api.doc(params={'hw': 'Headword to search.', 'inTran': 'Input transliteration. devanagari/slp1/iast/hk/wx/itrans/kolkata/velthuis', 'outTran': 'Output transliteration. devanagari/slp1/iast/hk/wx/itrans/kolkata/velthuis'})
class hwToAll2(Resource):
	"""Return the entries of this headword from all dictionaries."""

	get_parser = reqparse.RequestParser()

	@api.expect(get_parser, validate=True)
	def get(self, hw, inTran, outTran):
		final = {}
		for dict in cologne_dicts:
			sqlitepath = find_sqlite(dict)
			con = sqlite3.connect(sqlitepath)
			ans = con.execute("SELECT * FROM " + dict + " WHERE key = " + "'" + hw + "'")
			result = []
			for [headword, lnum, data] in ans.fetchall():
				result.append(block1(data, inTran, outTran))
			final[dict]  = result
		return jsonify(final)


@api.route('/' + apiversion + '/reg/<string:reg>')
@api.doc(params={'reg': 'Regex to search in all dictionaries.'})
class regToAll(Resource):
	"""Return the entries of this headword from all dictionaries."""

	get_parser = reqparse.RequestParser()

	@api.expect(get_parser, validate=True)
	def get(self, reg):
		final = {}
		for dict in cologne_dicts:
			sqlitepath = find_sqlite(dict)
			con = sqlite3.connect(sqlitepath)
			ans = con.execute("SELECT * FROM " + dict )
			result = []
			for [headword, lnum, data] in ans.fetchall():
				if re.search(reg, headword):
					result.append(block1(data))
			final[dict] = result
		return jsonify(final)


@api.route('/' + apiversion + '/dicts/<string:dict>/hw/<string:hw>/<string:inTran>/<string:outTran>')
@api.doc(params={'dict': 'Dictionary code.', 'hw': 'Headword to search.', 'inTran': 'Input transliteration. devanagari/slp1/iast/hk/wx/itrans/kolkata/velthuis', 'outTran': 'Output transliteration. devanagari/slp1/iast/hk/wx/itrans/kolkata/velthuis'})
class hwToData1(Resource):
	"""Return the JSON data regarding the given headword for given input transliteration and output transliteration."""

	get_parser = reqparse.RequestParser()

	@api.expect(get_parser, validate=True)
	def get(self, dict, hw, inTran, outTran):
		hw = sanscript.transliterate(hw, inTran, 'slp1')
		sqlitepath = find_sqlite(dict)
		con = sqlite3.connect(sqlitepath)
		ans = con.execute("SELECT * FROM " + dict + " WHERE key = " + "'" + hw + "'")
		result = []
		for [headword, lnum, data] in ans.fetchall():
			result.append(block1(data, inTran, outTran))
		final = {dict: result}
		return jsonify(final)


if __name__ == "__main__":
	app.run(debug=True)
