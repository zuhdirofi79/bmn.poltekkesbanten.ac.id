// API wrapper: GET user/praktikum/schedule.php
// Path: mobile/api/user/praktikum/schedule.dart
// Uses ApiService from lib. Envelope: { success, data, message? }

import '../../../lib/services/api_service.dart';
import '../../../lib/core/network/response_parser.dart';
import '../../../lib/core/network/api_exception.dart';
import '../../../lib/core/errors/feature_unavailable_exception.dart';
import '../../../lib/models/praktikum_schedule_model.dart';

const String _endpoint = 'user/praktikum/schedule.php';
const int _snippetMax = 300;

void _checkResponse(dynamic response, String endpoint) {
  final statusCode = response.statusCode ?? 0;
  final contentType = response.headers.value('content-type') ?? '';
  final ct = contentType.toLowerCase();
  // ignore: avoid_print
  print('[API][PRAKTIKUM] $endpoint status=$statusCode content-type=$contentType');
  if (statusCode == 404 || ct.contains('text/html')) {
    String? snippet;
    final data = response.data;
    if (data != null) {
      final s = data is String ? data : data.toString();
      snippet = s.length > _snippetMax ? s.substring(0, _snippetMax) : s;
    }
    throw FeatureUnavailableException(
      endpoint: endpoint,
      statusCode: statusCode,
      bodySnippet: snippet,
    );
  }
}

dynamic _parseEnvelope(Map<String, dynamic> map) {
  if (map['success'] != true) {
    final msg = map['message'] is String ? map['message'] as String : (map['message']?.toString() ?? 'Unknown error');
    throw ApiException(statusCode: 200, message: msg);
  }
  return map['data'];
}

class PraktikumScheduleApi {
  static Future<List<PraktikumScheduleModel>> getSchedule() async {
    final api = ApiService.instance;
    final response = await api.fetchGet(_endpoint);
    _checkResponse(response, _endpoint);

    final jsonMap = ResponseParser.parseJsonObject(response);
    final data = _parseEnvelope(jsonMap);
    if (data is! List) return [];

    final list = data as List;
    return list
        .where((e) => e is Map<String, dynamic>)
        .map((e) {
          final m = Map<String, dynamic>.from(e as Map);
          if (m.containsKey('tgl') && !m.containsKey('tanggal')) m['tanggal'] = m['tgl'];
          if (m.containsKey('ruang') && !m.containsKey('ruang_lab')) m['ruang_lab'] = m['ruang'];
          if (m.containsKey('waktu_mulai') && !m.containsKey('jam_mulai')) m['jam_mulai'] = m['waktu_mulai'];
          if (m.containsKey('waktu_selesai') && !m.containsKey('jam_selesai')) m['jam_selesai'] = m['waktu_selesai'];
          if (m.containsKey('kelas') && !m.containsKey('mata_kuliah')) m['mata_kuliah'] = m['kelas'];
          return PraktikumScheduleModel.fromJson(m);
        })
        .toList();
  }
}
