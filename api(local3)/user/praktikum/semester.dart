// API wrapper: GET user/praktikum/semester.php
// Path: mobile/api/user/praktikum/semester.dart
// Uses ApiService from lib. Envelope: { success, data, message? }

import '../../../lib/services/api_service.dart';
import '../../../lib/core/network/response_parser.dart';
import '../../../lib/core/network/api_exception.dart';
import '../../../lib/core/errors/feature_unavailable_exception.dart';
import '../../../lib/models/room_booking_master_data.dart';

const String _endpoint = 'user/praktikum/semester.php';
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

class PraktikumSemesterApi {
  static Future<SemesterInfoModel> getCurrentSemester() async {
    final api = ApiService.instance;
    final response = await api.fetchGet(_endpoint);
    _checkResponse(response, _endpoint);

    final jsonMap = ResponseParser.parseJsonObject(response);
    final data = _parseEnvelope(jsonMap);
    if (data is Map<String, dynamic>) {
      return SemesterInfoModel.fromJson(data);
    }
    if (data is int) {
      return SemesterInfoModel(id: data, label: 'Semester $data', aktif: true);
    }
    final sid = jsonMap['semester_id'];
    if (sid is int) return SemesterInfoModel(id: sid, label: 'Semester $sid', aktif: true);
    return SemesterInfoModel(id: 1, label: 'Semester 1', aktif: true);
  }
}
