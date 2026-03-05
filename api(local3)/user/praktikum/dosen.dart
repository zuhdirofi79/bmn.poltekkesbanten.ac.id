// API wrapper: GET user/praktikum/dosen.php
// Path: mobile/api/user/praktikum/dosen.dart
// Uses ApiService from lib. Envelope: { success, data, message? }

import '../../../lib/services/api_service.dart';
import '../../../lib/core/network/response_parser.dart';
import '../../../lib/core/network/api_exception.dart';
import '../../../lib/core/errors/feature_unavailable_exception.dart';
import '../../../lib/models/room_booking_master_data.dart';

const String _endpoint = 'user/praktikum/dosen.php';
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

class PraktikumDosenApi {
  static Future<List<DosenModel>> getDosenList() async {
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
          if (m.containsKey('nama') && !m.containsKey('name')) m['name'] = m['nama'];
          return DosenModel.fromJson(m);
        })
        .toList();
  }
}
